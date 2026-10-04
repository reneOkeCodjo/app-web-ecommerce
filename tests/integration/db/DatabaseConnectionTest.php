<?php

declare(strict_types=1);

namespace Tests\Integration\Db;

use Db_config\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    private \mysqli $connection;

    protected function setUp(): void
    {
        $this->connection = Database::connect();
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testDatabaseConnectionExecutesSelectOne(): void
    {
        $result = $this->connection->query('SELECT 1 AS connection_ok');
        $row = $result->fetch_assoc();

        self::assertSame(1, (int) $row['connection_ok']);
    }
}
