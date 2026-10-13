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
        self::assertSame('449.99', $products[0]->getPriceHt());
    }

    public function testFindByIdReturnsProductOrNull(): void
    {
        $repository = new InMemoryProductRepository([
            new Product(5, 'Tablette', 'Mug', '9.99', 10, '2026-04-01', '2026-04-01 08:00:00'),
        ]);

        self::assertNotNull($repository->findById(5));
        self::assertNull($repository->findById(99));
    }

    public function testCreateAssignsIncrementingIdsAndTimesIn(): void
    {
        $repository = new InMemoryProductRepository([]);

        $first = $repository->create('Smartphone', 'A', 1.0, '2026-05-01', 1);
        $second = $repository->create('Casque audio', 'B', 2.0, '2026-05-02', 2);

        self::assertSame(1, $first->getId());
        self::assertSame(2, $second->getId());
        self::assertSame('Smartphone', $first->getType());
        self::assertNotSame('', $first->getDateNew());
        self::assertCount(2, $repository->findAll());
    }

    public function testUpdateReplacesExistingProduct(): void
    {
        $repository = new InMemoryProductRepository([
            new Product(1, 'old', 'Old', '5.00', 3, '2026-01-01', '2026-01-01 00:00:00'),
        ]);

        $updated = $repository->update(
            new Product(1, 'new', 'New', '8.50', 7, '2026-06-01', '2026-06-01 12:00:00')
        );

        self::assertSame('New', $updated->getDesignation());
        self::assertSame('New', $repository->findById(1)?->getDesignation());
        self::assertSame('8.50', $repository->findById(1)?->getPriceHt());
        self::assertSame(7, $repository->findById(1)?->getStock());
    }

    public function testUpdateThrowsWhenMissing(): void
    {
        $repository = new InMemoryProductRepository([]);

        $this->expectException(InvalidArgumentException::class);
        $repository->update(
            new Product(1, 'x', 'Missing', '1.00', 0, '2026-01-01', '2026-01-01 00:00:00')
        );
    }

    public function testDeleteRemovesProduct(): void
    {
        $repository = new InMemoryProductRepository([
            new Product(1, 'x', 'Gone', '1.00', 0, '2026-01-01', '2026-01-01 00:00:00'),
        ]);

        self::assertTrue($repository->delete(1));
        self::assertFalse($repository->delete(1));
        self::assertSame([], $repository->findAll());
    }
}
