<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DAO;

use App\Domain\Model\Product;
use App\Infrastructure\Http\SessionStoreInterface;

/**
 * Session-backed mock product store for the admin UI.
 */
final class SessionProductRepository
{
    private const SESSION_KEY = 'mock_produits_v2';

    private InMemoryProductRepository $inner;

    public function __construct(private SessionStoreInterface $session)
    {
        $this->inner = new InMemoryProductRepository($this->loadSeed());
    }

    /**
     * @return list<Product>
     */
    public function findAll(): array
    {
        return $this->inner->findAll();
    }

    public function findById(int $id): ?Product
    {
        return $this->inner->findById($id);
    }

    public function create(
        string $type,
        string $designation,
        float $prixHt,
        string $dateIn,
        int $stock = 0
    ): Product {
        $product = $this->inner->create($type, $designation, $prixHt, $dateIn, $stock);
        $this->persist();

        return $product;
    }

    public function update(Product $product): Product
    {
        $updated = $this->inner->update($product);
        $this->persist();

        return $updated;
    }

    public function delete(int $id): bool
    {
        $deleted = $this->inner->delete($id);
        if ($deleted) {
            $this->persist();
        }

        return $deleted;
    }

    /**
     * @return list<Product>|null
     */
    private function loadSeed(): ?array
    {
        $raw = $this->session->get(self::SESSION_KEY);
        if (!is_array($raw) || $raw === []) {
            return null;
        }

        $products = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $products[] = new Product(
                (int) ($row['id'] ?? 0),
                (string) ($row['type'] ?? ''),
                (string) ($row['designation'] ?? ''),
                number_format((float) ($row['prix_ht'] ?? 0), 2, '.', ''),
                (int) ($row['stock'] ?? 0),
                (string) ($row['date_in'] ?? ''),
                (string) ($row['times_in'] ?? '')
            );
        }

        return $products === [] ? null : $products;
    }

    private function persist(): void
    {
        $payload = [];
        foreach ($this->inner->findAll() as $product) {
            $payload[] = [
                'id' => $product->getId(),
                'type' => $product->getType(),
                'designation' => $product->getDesignation(),
                'prix_ht' => (float) $product->getPriceHt(),
                'date_in' => $product->getDateIn(),
                'times_in' => $product->getDateNew() ?? '',
                'stock' => $product->getStock(),
            ];
        }

        $this->session->set(self::SESSION_KEY, $payload);
    }
}
