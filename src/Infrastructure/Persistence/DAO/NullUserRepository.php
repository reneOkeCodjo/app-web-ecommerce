<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DAO;

use App\Domain\Model\User;

/**
 * No-op repository for request paths that only need session inspection.
 */
final class NullUserRepository implements UserRepositoryInterface
{
    public function findByUsername(string $username): ?User
    {
        return null;
    }
}
