<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Application\Services\Auth;
use App\Domain\Model\User;
use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    public function testLoginSucceedsWithValidCredentialsAndStoresSession(): void
    {
        $password = 'correct-password';
        $repository = $this->repositoryReturningUser($password);
        $session = $this->memorySession();

        $auth = new Auth($repository, $session);

        self::assertTrue($auth->login('auth_test_user', $password));
        self::assertTrue($auth->isAuthenticated());
        self::assertSame(1, $auth->getAuthenticatedUserId());
        self::assertSame(1, $session->get('user_id'));
        self::assertTrue($session->wasRegenerated());
    }

    public function testLoginRejectsInvalidPasswordAndDoesNotStoreSession(): void
    {
        $repository = $this->repositoryReturningUser('correct-password');
        $session = $this->memorySession();

        $auth = new Auth($repository, $session);

        self::assertFalse($auth->login('auth_test_user', 'wrong-password'));
        self::assertFalse($auth->isAuthenticated());
        self::assertNull($session->get('user_id'));
    }

    public function testLoginRejectsUnknownUser(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(null);
        $session = $this->memorySession();

        $auth = new Auth($repository, $session);

        self::assertFalse($auth->login('missing_auth_test_user', 'correct-password'));
        self::assertFalse($auth->isAuthenticated());
    }

    public function testLogoutClearsSession(): void
    {
        $password = 'correct-password';
        $session = $this->memorySession();
        $auth = new Auth($this->repositoryReturningUser($password), $session);

        $auth->login('auth_test_user', $password);
        self::assertTrue($auth->isAuthenticated());

        $auth->logout();

        self::assertFalse($auth->isAuthenticated());
        self::assertNull($auth->getAuthenticatedUserId());
        self::assertTrue($session->wasDestroyed());
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

    /**
     * @return SessionStoreInterface&object{wasRegenerated(): bool, wasDestroyed(): bool}
     */
    private function memorySession(): SessionStoreInterface
    {
        return new class implements SessionStoreInterface {
            /** @var array<string, mixed> */
            private array $values = [];
            private bool $regenerated = false;
            private bool $destroyed = false;

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
                $this->regenerated = true;
            }

            public function destroy(): void
            {
                $this->values = [];
                $this->destroyed = true;
            }

            public function wasRegenerated(): bool
            {
                return $this->regenerated;
            }

            public function wasDestroyed(): bool
            {
                return $this->destroyed;
            }
        };
    }
}
