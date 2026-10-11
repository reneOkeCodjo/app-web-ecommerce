<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DAO;

use App\Domain\Model\Product;

/**
 * Product persistence contract.
 * Real SQL DAO will implement this later; admin uses a mock for now.
 */
interface ProductRepositoryInterface
{
    /**
     * @return list<Product>
     */
    public function findAll(): array;

    public function findById(int $id): ?Product;

    public function create(
        string $name,
        string $description,
        float $price,
        int $stock
    ): Product;

    public function update(Product $product): Product;

    public function delete(int $id): bool;
}
