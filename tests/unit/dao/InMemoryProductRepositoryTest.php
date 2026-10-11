<?php

declare(strict_types=1);

namespace Tests\Unit\Dao;

use App\Domain\Model\Product;
use App\Infrastructure\Persistence\DAO\InMemoryProductRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InMemoryProductRepositoryTest extends TestCase
{
    public function testFindAllReturnsSeededProducts(): void
    {
        $repository = new InMemoryProductRepository();

        $products = $repository->findAll();

        self::assertCount(3, $products);
        self::assertSame('Classic Tee', $products[0]->getName());
    }

    public function testFindByIdReturnsProductOrNull(): void
    {
        $repository = new InMemoryProductRepository([
            new Product(5, 'Mug', 'Ceramic mug', 9.99, 10),
        ]);

        self::assertNotNull($repository->findById(5));
        self::assertNull($repository->findById(99));
    }

    public function testCreateAssignsIncrementingIds(): void
    {
        $repository = new InMemoryProductRepository([]);

        $first = $repository->create('A', 'desc A', 1.0, 1);
        $second = $repository->create('B', 'desc B', 2.0, 2);

        self::assertSame(1, $first->getId());
        self::assertSame(2, $second->getId());
        self::assertCount(2, $repository->findAll());
    }

    public function testUpdateReplacesExistingProduct(): void
    {
        $repository = new InMemoryProductRepository([
            new Product(1, 'Old', 'old desc', 5.0, 3),
        ]);

        $updated = $repository->update(new Product(1, 'New', 'new desc', 8.5, 7));

        self::assertSame('New', $updated->getName());
        self::assertSame('New', $repository->findById(1)?->getName());
        self::assertSame(8.5, $repository->findById(1)?->getPrice());
    }

    public function testUpdateThrowsWhenMissing(): void
    {
        $repository = new InMemoryProductRepository([]);

        $this->expectException(InvalidArgumentException::class);
        $repository->update(new Product(1, 'Missing', '', 1.0, 0));
    }

    public function testDeleteRemovesProduct(): void
    {
        $repository = new InMemoryProductRepository([
            new Product(1, 'Gone', '', 1.0, 0),
        ]);

        self::assertTrue($repository->delete(1));
        self::assertFalse($repository->delete(1));
        self::assertSame([], $repository->findAll());
    }
}
