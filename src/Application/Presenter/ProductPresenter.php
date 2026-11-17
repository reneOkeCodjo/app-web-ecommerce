<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// Ce présentateur appartient à la couche « Application ».
namespace App\Application\Presenter;

// On utilise le modèle et le calcul des prix.
use App\Domain\Model\Product;
use App\Domain\Pricing\ProductPricing;

/**
 * Transforme un objet Product en tableau simple pour les pages et le JavaScript.
 *
 * On évite ainsi de recopier la même conversion dans plusieurs fichiers.
 */
final class ProductPresenter
{
    /**
     * Renvoie les données d'un produit sous forme de tableau.
     *
     * @return array{
     *     id: int,
     *     type: string,
     *     designation: string,
     *     prixHt: float,
     *     promotion: float|null,
     *     prixTtc: float,
     *     prixDisponible: float,
     *     dateIn: string,
     *     timesIn: string,
     *     stock: int,
     *     image: string|null
     * }
     */
    public static function toArray(Product $product): array
    {
        // On convertit les valeurs numériques une seule fois.
        $priceHt = (float) $product->getPriceHt();
        $promotion = $product->getPromotion() !== null
            ? (float) $product->getPromotion()
            : null;

        // On renvoie toutes les informations utiles à l'affichage.
        return [
            'id' => (int) $product->getId(),
            'type' => $product->getType(),
            'designation' => $product->getDesignation(),
            'prixHt' => $priceHt,
            'promotion' => $promotion,
            'prixTtc' => ProductPricing::priceTtc($priceHt),
            'prixDisponible' => ProductPricing::priceAvailable($priceHt, $promotion),
            'dateIn' => $product->getDateIn(),
            'timesIn' => $product->getDateNew() ?? '',
            'stock' => $product->getStock(),
            'image' => $product->getImage(),
        ];
    }

    /**
     * Transforme une liste de produits en tableau de tableaux.
     *
     * @param list<Product> $products
     * @return list<array<string, mixed>>
     */
    public static function collection(array $products): array
    {
        return array_map(
            static fn (Product $product): array => self::toArray($product),
            $products
        );
    }

    /**
     * Encode les données pour une balise JSON sécurisée.
     *
     * @param array<string, mixed>|list<array<string, mixed>> $data
     */
    public static function json(array $data): string
    {
        return (string) json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }
}
