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

        self::assertCount(6, $products);
        self::assertSame('Galaxy A55', $products[0]->getDesignation());
        self::assertSame('Smartphone', $products[0]->getType());
        self::assertSame(449.99, $products[0]->getPrixHt());
    }

    public function testFindByIdReturnsProductOrNull(): void
    {
        $repository = new InMemoryProductRepository([
            new Product(5, 'vaisselle', 'Mug', 9.99, '2026-04-01', '2026-04-01 08:00:00', 10),
        ]);

        self::assertNotNull($repository->findById(5));
        self::assertNull($repository->findById(99));
    }

    public function testCreateAssignsIncrementingIdsAndTimesIn(): void
    {
        $repository = new InMemoryProductRepository([]);

        $first = $repository->create('vetement', 'A', 1.0, '2026-05-01', 1);
        $second = $repository->create('accessoire', 'B', 2.0, '2026-05-02', 2);

        self::assertSame(1, $first->getId());
        self::assertSame(2, $second->getId());
        self::assertSame('vetement', $first->getType());
        self::assertNotSame('', $first->getTimesIn());
        self::assertCount(2, $repository->findAll());
    }

    public function testUpdateReplacesExistingProduct(): void
    {
        $repository = new InMemoryProductRepository([
            new Product(1, 'old', 'Old', 5.0, '2026-01-01', '2026-01-01 00:00:00', 3),
        ]);

        $updated = $repository->update(
            new Product(1, 'new', 'New', 8.5, '2026-06-01', '2026-06-01 12:00:00', 7)
        );

        self::assertSame('New', $updated->getDesignation());
        self::assertSame('New', $repository->findById(1)?->getDesignation());
        self::assertSame(8.5, $repository->findById(1)?->getPrixHt());
        self::assertSame(7, $repository->findById(1)?->getStock());
    }

    public function testUpdateThrowsWhenMissing(): void
    {
        $repository = new InMemoryProductRepository([]);

        $this->expectException(InvalidArgumentException::class);
        $repository->update(
            new Product(1, 'x', 'Missing', 1.0, '2026-01-01', '2026-01-01 00:00:00', 0)
        );
    }

    public function testDeleteRemovesProduct(): void
    {
        $repository = new InMemoryProductRepository([
            new Product(1, 'x', 'Gone', 1.0, '2026-01-01', '2026-01-01 00:00:00', 0),
        ]);

        self::assertTrue($repository->delete(1));
        self::assertFalse($repository->delete(1));
        self::assertSame([], $repository->findAll());
    }
}
