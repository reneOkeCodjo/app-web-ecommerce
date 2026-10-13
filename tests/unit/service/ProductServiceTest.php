<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Application\Services\ProductService;
use App\Domain\Model\Product;
use App\Infrastructure\Persistence\DAO\ProductRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ProductServiceTest extends TestCase
{
    public function testCreatePersistsValidatedProduct(): void
    {
        $repo = new class implements ProductRepositoryInterface {
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
        };

        $service = new ProductService($repo);

        self::assertTrue(
            $service->create(
                'Smartphone',
                'Galaxy A55',
                '449.99',
                '10',
                '2026-01-10',
                '10.00',
                '/uploads/galaxy.jpg'
            )
        );

        self::assertNotNull($repo->created);
        self::assertSame('Galaxy A55', $repo->created->getDesignation());
        self::assertSame('10.00', $repo->created->getPromotion());
        self::assertSame('/uploads/galaxy.jpg', $repo->created->getImage());
    }

    public function testCreateThrowsOnInvalidPayload(): void
    {
        $repo = new class implements ProductRepositoryInterface {
            public function create(Product $product): bool
            {
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
        };

        $service = new ProductService($repo);

        $this->expectException(InvalidArgumentException::class);
        $service->create('Inconnu', 'X', '10.00', '1', '2026-01-10');
    }
}
