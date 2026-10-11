<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DAO;

use App\Domain\Model\Product;
use InvalidArgumentException;

/**
 * In-memory mock product repository (no database).
 * Shape matches table `produit`. Replace with a real DAO later.
 */
final class InMemoryProductRepository implements ProductRepositoryInterface
{
    /** @var array<int, Product> */
    private array $products = [];

    private int $nextId = 1;

    /**
     * @param list<Product>|null $seed Optional products; defaults to demo catalog.
     */
    public function __construct(?array $seed = null)
    {
        if ($seed === null) {
            $seed = [
                new Product(
                    1,
                    'vetement',
                    'Classic Tee',
                    19.99,
                    '2026-01-10',
                    '2026-01-10 09:15:00',
                    40
                ),
                new Product(
                    2,
                    'accessoire',
                    'Cap',
                    14.50,
                    '2026-02-01',
                    '2026-02-01 14:30:00',
                    25
                ),
                new Product(
                    3,
                    'accessoire',
                    'Tote Bag',
                    24.00,
                    '2026-03-12',
                    '2026-03-12 11:05:00',
                    15
                ),
            ];
        }

        foreach ($seed as $product) {
            $this->products[$product->getId()] = $product;
            $this->nextId = max($this->nextId, $product->getId() + 1);
        }
    }

    public function findAll(): array
    {
        return array_values($this->products);
    }

    public function findById(int $id): ?Product
    {
        return $this->products[$id] ?? null;
    }

    public function create(
        string $type,
        string $designation,
        float $prixHt,
        string $dateIn,
        int $stock = 0
    ): Product {
        $product = new Product(
            $this->nextId++,
            $type,
            $designation,
            $prixHt,
            $dateIn,
            date('Y-m-d H:i:s'),
            $stock
        );
        $this->products[$product->getId()] = $product;

        return $product;
    }

    public function update(Product $product): Product
    {
        if (!isset($this->products[$product->getId()])) {
            throw new InvalidArgumentException('Product not found.');
        }

        $this->products[$product->getId()] = $product;

        return $product;
    }

    public function delete(int $id): bool
    {
        if (!isset($this->products[$id])) {
            return false;
        }

        unset($this->products[$id]);

        return true;
    }
}
