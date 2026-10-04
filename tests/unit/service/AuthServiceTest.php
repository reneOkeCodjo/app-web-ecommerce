<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Application\Services\Auth;
use App\Domain\Model\User;
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    public function testLoginSucceedsWithValidCredentials(): void
    {
        $password = 'correct-password';
        $repository = $this->repositoryReturningUser($password);

        $auth = new Auth($repository);

        self::assertTrue($auth::login('auth_test_user', $password));
    }

    public function testLoginRejectsInvalidPassword(): void
    {
        $repository = $this->repositoryReturningUser('correct-password');

        $auth = new Auth($repository);

        self::assertFalse($auth::login('auth_test_user', 'wrong-password'));
    }

    public function testLoginRejectsUnknownUser(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(null);

        $auth = new Auth($repository);

        self::assertFalse($auth::login('missing_auth_test_user', 'correct-password'));
    }

    private function repositoryReturningUser(string $password): UserRepositoryInterface
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(
            new User(
                1,
                'auth_test_user',
                'auth-test@example.com',
                password_hash($password, PASSWORD_DEFAULT)
            )
        );

        return $repository;
    }
}
