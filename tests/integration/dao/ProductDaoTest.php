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
}