<?php

declare(strict_types=1);

namespace Tests\Unit\Dao;

use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\SessionProductRepository;
use PHPUnit\Framework\TestCase;

final class SessionProductRepositoryTest extends TestCase
{
    public function testCreatePersistsAcrossRepositoryInstances(): void
    {
        $session = $this->memorySession();
        $first = new SessionProductRepository($session);

        $created = $first->create('vetement', 'Hoodie', 39.9, '2026-07-01', 8);

        $second = new SessionProductRepository($session);
        $found = $second->findById($created->getId());

        self::assertNotNull($found);
        self::assertSame('Hoodie', $found->getDesignation());
        self::assertSame(8, $found->getStock());
    }

    public function testDeleteRemovesProductFromSession(): void
    {
        $session = $this->memorySession();
        $repository = new SessionProductRepository($session);
        $id = $repository->findAll()[0]->getId();

        self::assertTrue($repository->delete($id));
        self::assertNull((new SessionProductRepository($session))->findById($id));
    }

    private function memorySession(): SessionStoreInterface
    {
        return new class implements SessionStoreInterface {
            /** @var array<string, mixed> */
            private array $values = [];

            public function set(string $key, mixed $value): void
            {
                $this->values[$key] = $value;
            }

            public function get(string $key): mixed
            {
                return $this->values[$key] ?? null;
            }

            public function remove(string $key): void
            {
                unset($this->values[$key]);
            }

            public function regenerate(): void
            {
            }

            public function destroy(): void
            {
                $this->values = [];
            }
        };
    }
}
