<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Application\Services\Product\ProductService;
use App\Domain\Model\Product;
use App\Infrastructure\Persistence\DAO\ProductRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ProductServiceTest extends TestCase
{
    private FakeProductRepository $repository;
    private ProductService $service;

    protected function setUp(): void
    {
        $this->repository = new FakeProductRepository();
        $this->service = new ProductService($this->repository);
    }

    public function testCreatePersistsValidatedProduct(): void
    {
        self::assertTrue(
            $this->service->create(
                'Smartphone',
                'Galaxy A55',
                '449.99',
                '10',
                '2026-01-10',
                '10.00',
                '/uploads/galaxy.jpg'
            )
        );

        self::assertNotNull($this->repository->created);
        self::assertSame('Galaxy A55', $this->repository->created->getDesignation());
        self::assertSame('10.00', $this->repository->created->getPromotion());
        self::assertSame('/uploads/galaxy.jpg', $this->repository->created->getImage());
    }

    public function testCreateThrowsOnInvalidPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->create('Inconnu', 'X', '10.00', '1', '2026-01-10');
    }
}

/**
 * In-memory stand-in so ProductService unit tests stay off MySQL.
 */
final class FakeProductRepository implements ProductRepositoryInterface
{
    public ?Product $created = null;

    public function create(Product $product): bool
    {
        $this->created = $product;

        return true;
    }

    public function findAll(): array
    {
        return [];
    }

    public function findById(int $id): ?Product
    {
        return null;
    }

    public function update(Product $product): bool
    {
        return true;
    }

    public function delete(int $id): bool
    {
        return true;
    }
}
