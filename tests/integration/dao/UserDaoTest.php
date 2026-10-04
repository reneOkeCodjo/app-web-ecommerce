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
        $this->connection->begin_transaction();
        $this->connection->query(
            "INSERT INTO compte (compte_type, compte_description)
             VALUES ('dao-test', 'DAO integration test account')"
        );
        $compteId = $this->connection->insert_id;

        $this->connection->query(
            "INSERT INTO users (user_login, user_mail, user_password, user_compte_id)
             VALUES ('dao_test_user', 'dao-test@example.com', 'hashed-password', {$compteId})"
        );

        $this->repository = new UserRepository($this->connection);
    }

    protected function tearDown(): void
    {
        $this->connection->rollback();
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
