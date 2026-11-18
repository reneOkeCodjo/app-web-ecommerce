<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DAO;

interface CartRepositoryInterface
{
    /**
     * @return array<int, int>
     */
    public function findItems(int $userId): array;

    /**
     * @param array<int, int> $items
     */
    public function replaceItems(int $userId, array $items): void;

    public function clear(int $userId): void;
}
