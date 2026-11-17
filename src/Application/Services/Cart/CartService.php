<?php

declare(strict_types=1);

namespace App\Application\Services\Cart;

use App\Domain\Model\Product;
use App\Domain\Pricing\ProductPricing;
use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\ProductRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

final class CartService
{
    // Le panier est conservé dans la session PHP, sans nouvelle table MySQL.
    private const SESSION_KEY = 'cart_items';
    // Les frais sont gratuits à partir du seuil défini ci-dessous.
    private const SHIPPING_COST = 5.99;
    private const FREE_SHIPPING_THRESHOLD = 50.00;

    public function __construct(
        private ProductRepositoryInterface $products,
        private SessionStoreInterface $session
    ) {
    }

    public function add(int $productId, int $quantity = 1): void
    {
        // Une quantité ajoutée doit toujours être positive.
        if ($quantity < 1) {
            throw new InvalidArgumentException('La quantité doit être positive.');
        }

        // Le produit est relu depuis MySQL afin de vérifier le stock réel.
        $product = $this->findProduct($productId);
        $items = $this->readItems();
        $newQuantity = ($items[$productId] ?? 0) + $quantity;

        // On vérifie la quantité totale, y compris les exemplaires déjà présents.
        $this->assertStock($product, $newQuantity);
        $items[$productId] = $newQuantity;
        $this->writeItems($items);
    }

    public function setQuantity(int $productId, int $quantity): void
    {
        // Zéro signifie retirer le produit ; une quantité négative est interdite.
        if ($quantity < 0) {
            throw new InvalidArgumentException('La quantité ne peut pas être négative.');
        }

        if ($quantity === 0) {
            $this->remove($productId);
            return;
        }

        $product = $this->findProduct($productId);
        $this->assertStock($product, $quantity);
        $items = $this->readItems();
        $items[$productId] = $quantity;
        $this->writeItems($items);
    }

    public function remove(int $productId): void
    {
        $items = $this->readItems();
        unset($items[$productId]);
        $this->writeItems($items);
    }

    public function clear(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    /**
     * @return array{items: list<array<string, mixed>>, totals: array<string, float|int>}
     */
    public function snapshot(): array
    {
        // Construit l'état complet du panier à partir des prix actuels de la base.
        $items = [];
        $subtotalHt = 0.0;
        $vat = 0.0;
        $subtotalTtc = 0.0;
        $totalQuantity = 0;

        foreach ($this->readItems() as $productId => $quantity) {
            $product = $this->products->findById($productId);
            // Un produit supprimé ou épuisé n'est pas affiché dans le panier.
            if ($product === null || $product->getStock() < 1) {
                continue;
            }

            $quantity = min($quantity, $product->getStock());
            // La promotion est appliquée au prix HT avant de calculer la TVA.
            $unitHt = $this->discountedPriceHt($product);
            $lineHt = round($unitHt * $quantity, 2);
            $lineVat = round($lineHt * ProductPricing::TVA_RATE, 2);
            $lineTtc = round($lineHt + $lineVat, 2);

            $items[] = [
                'id' => $productId,
                'designation' => $product->getDesignation(),
                'image' => $product->getImage(),
                'quantity' => $quantity,
                'stock' => $product->getStock(),
                'unitHt' => round($unitHt, 2),
                'unitTtc' => round($unitHt * (1 + ProductPricing::TVA_RATE), 2),
                'lineHt' => $lineHt,
                'lineVat' => $lineVat,
                'lineTtc' => $lineTtc,
            ];

            $subtotalHt += $lineHt;
            $vat += $lineVat;
            $subtotalTtc += $lineTtc;
            $totalQuantity += $quantity;
        }

        // Un panier vide ne doit pas générer de frais d'expédition.
        $shipping = $subtotalTtc >= self::FREE_SHIPPING_THRESHOLD || $subtotalTtc === 0.0
            ? 0.0
            : self::SHIPPING_COST;

        return [
            'items' => $items,
            'totals' => [
                'quantity' => $totalQuantity,
                'subtotalHt' => round($subtotalHt, 2),
                'vat' => round($vat, 2),
                'subtotalTtc' => round($subtotalTtc, 2),
                'shipping' => $shipping,
                'total' => round($subtotalTtc + $shipping, 2),
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function readItems(): array
    {
        // Les données de session sont normalisées en identifiants et quantités positifs.
        $raw = $this->session->get(self::SESSION_KEY);
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $productId => $quantity) {
            if ((is_int($productId) || ctype_digit((string) $productId)) && is_numeric($quantity)) {
                $id = (int) $productId;
                $count = (int) $quantity;
                if ($id > 0 && $count > 0) {
                    $items[$id] = $count;
                }
            }
        }

        return $items;
    }

    /**
     * @param array<int, int> $items
     */
    private function writeItems(array $items): void
    {
        // On supprime la clé de session lorsque le panier devient vide.
        if ($items === []) {
            $this->clear();
            return;
        }

        $this->session->set(self::SESSION_KEY, $items);
    }

    private function findProduct(int $productId): Product
    {
        // Les identifiants sont toujours validés avec le repository côté serveur.
        if ($productId <= 0) {
            throw new InvalidArgumentException('Produit invalide.');
        }

        $product = $this->products->findById($productId);
        if ($product === null) {
            throw new RuntimeException('Produit introuvable.');
        }

        return $product;
    }

    private function assertStock(Product $product, int $quantity): void
    {
        // Cette vérification protège l'ajout et la modification de quantité.
        if ($product->getStock() < 1 || $quantity > $product->getStock()) {
            throw new InvalidArgumentException('La quantité demandée dépasse le stock disponible.');
        }
    }

    private function discountedPriceHt(Product $product): float
    {
        // Le prix du panier respecte la promotion éventuelle du produit.
        $price = (float) $product->getPriceHt();
        $promotion = $product->getPromotion();

        if ($promotion === null || (float) $promotion <= 0.0) {
            return $price;
        }

        return $price * (1 - ((float) $promotion / 100));
    }
}
