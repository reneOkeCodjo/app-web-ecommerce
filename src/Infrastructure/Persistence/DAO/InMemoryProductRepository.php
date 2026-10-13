<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DAO;

use App\Domain\Model\Product;
use InvalidArgumentException;

/**
 * In-memory mock product repository (no database).
 * Temporary admin UI store until pages switch to ProductRepository.
 */
final class InMemoryProductRepository
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
                new Product(1, 'Smartphone', 'Galaxy A55', '449.99', 40, '2026-01-10', '2026-01-10 09:15:00'),
                new Product(2, 'Ordinateur portable', 'ThinkPad E14', '899.00', 18, '2026-02-01', '2026-02-01 14:30:00'),
                new Product(3, 'Casque audio', 'Sony WH-1000XM5', '349.90', 25, '2026-03-12', '2026-03-12 11:05:00'),
                new Product(4, 'Montre connectée', 'Apple Watch SE', '279.00', 30, '2026-04-05', '2026-04-05 16:20:00'),
                new Product(5, 'Tablette', 'iPad 10', '529.00', 12, '2026-05-18', '2026-05-18 10:00:00'),
                new Product(6, 'Caméra', 'GoPro Hero 12', '399.00', 15, '2026-06-02', '2026-06-02 13:45:00'),
            ];
        }

        foreach ($seed as $product) {
            $id = $product->getId();
            if ($id === null) {
                continue;
            }
            $this->products[$id] = $product;
            $this->nextId = max($this->nextId, $id + 1);
        }
    }

    /**
     * @return list<Product>
     */
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
            number_format($prixHt, 2, '.', ''),
            $stock,
            $dateIn,
            date('Y-m-d H:i:s')
        );
        $id = $product->getId();
        if ($id !== null) {
            $this->products[$id] = $product;
        }

        return $product;
    }

    public function update(Product $product): Product
    {
        $id = $product->getId();
        if ($id === null || !isset($this->products[$id])) {
            throw new InvalidArgumentException('Product not found.');
        }

        $this->products[$id] = $product;

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
