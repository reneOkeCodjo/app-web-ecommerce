<?php

declare(strict_types=1);

namespace Tests\Integration\Dao;

use App\Infrastructure\Persistence\DAO\UserRepository;
use Db_config\Database;
use PHPUnit\Framework\TestCase;

final class UserDaoTest extends TestCase
{
    private \mysqli $connection;
    private UserRepository $repository;

    protected function setUp(): void
    {
        $this->connection = Database::connect();
        $this->connection->query(
            'CREATE TEMPORARY TABLE users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL
            )'
        );
        $this->connection->query(
            "INSERT INTO users (username, email, password)
             VALUES ('dao_test_user', 'dao-test@example.com', 'hashed-password')"
        );

        $this->repository = new UserRepository($this->connection);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testFindByUsernameReturnsSeededUser(): void
    {
        $user = $this->repository->findByUsername('dao_test_user');

        self::assertNotNull($user);
        self::assertSame('dao_test_user', $user->getUsername());
        self::assertSame('dao-test@example.com', $user->getEmail());
        self::assertSame('hashed-password', $user->getPassword());
    }

    public function testFindByUsernameReturnsNullForUnknownUser(): void
    {
        self::assertNull($this->repository->findByUsername('missing_dao_test_user'));
    }
}
