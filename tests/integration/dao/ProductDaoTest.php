<?php

declare(strict_types=1);

namespace Tests\Integration\Dao;

use App\Domain\Model\Product;
use App\Infrastructure\Persistence\DAO\ProductRepository;
use Db_config\Database;
use PHPUnit\Framework\TestCase;

final class ProductDaoTest extends TestCase
{
    private \mysqli $connection;
    private ProductRepository $repository;

    protected function setUp(): void
    {
        $this->connection = Database::connect();
        $this->connection->begin_transaction();
        $this->repository = new ProductRepository($this->connection);
    }

    protected function tearDown(): void
    {
        $this->connection->rollback();
        $this->connection->close();
    }

    public function testCreateFindUpdateDeleteWithPromotionAndImage(): void
    {
        $created = $this->repository->create(new Product(
            null,
            'Casque audio',
            'Casque test CRUD',
            '30.00',
            5,
            date('Y-m-d'),
            null,
            '10.00',
            '/uploads/products/casque.jpg'
        ));

        self::assertTrue($created);

        $id = (int) $this->connection->insert_id;
        self::assertGreaterThan(0, $id);

        $found = $this->repository->findById($id);

        self::assertNotNull($found);
        self::assertSame('Casque test CRUD', $found->getDesignation());
        self::assertSame('30.00', $found->getPriceHt());
        self::assertSame(5, $found->getStock());
        self::assertSame('10.00', $found->getPromotion());
        self::assertSame('/uploads/products/casque.jpg', $found->getImage());

        $updated = $this->repository->update(new Product(
            $id,
            'Casque audio',
            'Casque modifié',
            '25.00',
            10,
            date('Y-m-d'),
            $found->getDateNew(),
            '15.50',
            '/uploads/products/casque-v2.jpg'
        ));

        self::assertTrue($updated);

        $afterUpdate = $this->repository->findById($id);

        self::assertNotNull($afterUpdate);
        self::assertSame('Casque modifié', $afterUpdate->getDesignation());
        self::assertSame('25.00', $afterUpdate->getPriceHt());
        self::assertSame(10, $afterUpdate->getStock());
        self::assertSame('15.50', $afterUpdate->getPromotion());
        self::assertSame('/uploads/products/casque-v2.jpg', $afterUpdate->getImage());

        self::assertTrue($this->repository->delete($id));
        self::assertNull($this->repository->findById($id));
    }

    public function testFindByIdReturnsNullForUnknownProduct(): void
    {
        self::assertNull($this->repository->findById(999999));
    }
}
