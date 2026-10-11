<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DAO;

use App\Domain\Model\Product;
use InvalidArgumentException;

/**
 * In-memory mock product repository (no database).
 * Replace with a real ProductDAO when the products table is ready.
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
                new Product(1, 'Classic Tee', 'Soft cotton t-shirt', 19.99, 40),
                new Product(2, 'Cap', 'Adjustable everyday cap', 14.50, 25),
                new Product(3, 'Tote Bag', 'Canvas tote bag', 24.00, 15),
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
        string $name,
        string $description,
        float $price,
        int $stock
    ): Product {
        $product = new Product($this->nextId++, $name, $description, $price, $stock);
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
