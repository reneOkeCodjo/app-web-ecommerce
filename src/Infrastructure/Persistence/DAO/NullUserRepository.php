<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DAO;

use App\Domain\Model\User;
use RuntimeException;

/**
 * No-op repository for request paths that only need session inspection.
 */
final class NullUserRepository implements UserRepositoryInterface
{
    public function findByUsername(string $username): ?User
    {
        return null;
    }

    public function findByEmail(string $email): ?User
    {
        return null;
    }

    public function create(string $username, string $email, string $passwordHash, int $compteId): User
    {
        throw new RuntimeException('NullUserRepository cannot create users.');
    }

    public function findCompteIdByType(string $type): ?int
    {
        return null;
    }
}
