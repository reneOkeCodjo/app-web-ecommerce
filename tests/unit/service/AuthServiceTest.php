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
        self::assertFalse($auth->isAdmin());
        self::assertSame(1, $auth->getAuthenticatedUserId());
        self::assertSame('client', $auth->getCompteType());
        self::assertSame(1, $session->get('user_id'));
        self::assertSame('client', $session->get('compte_type'));
        self::assertTrue($session->wasRegenerated());
    }

    public function testLoginStoresAdminCompteType(): void
    {
        $password = 'correct-password';
        $repository = $this->repositoryReturningUser($password, 'admin');
        $session = $this->memorySession();
        $auth = new Auth($repository, $session);

        self::assertTrue($auth->login('auth_test_user', $password));
        self::assertTrue($auth->isAdmin());
        self::assertSame('admin', $auth->getCompteType());
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

    public function testRegisterCreatesUserAndStoresSession(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(null);
        $repository->method('findByEmail')->willReturn(null);
        $repository->method('findCompteIdByType')->with('client')->willReturn(1);
        $repository->method('create')->willReturnCallback(
            function (string $username, string $email, string $hash, int $compteId): User {
                self::assertSame('new_user', $username);
                self::assertSame('new@example.com', $email);
                self::assertSame(1, $compteId);
                self::assertTrue(password_verify('Password1!', $hash));

                return new User(10, $username, $email, $hash, 'client');
            }
        );

        $session = $this->memorySession();
        $auth = new Auth($repository, $session);

        self::assertTrue($auth->register('new_user', 'new@example.com', 'Password1!'));
        self::assertTrue($auth->isAuthenticated());
        self::assertFalse($auth->isAdmin());
        self::assertSame(10, $auth->getAuthenticatedUserId());
        self::assertSame('client', $session->get('compte_type'));
        self::assertTrue($session->wasRegenerated());
    }

    public function testRegisterRejectsExistingUsername(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(
            new User(1, 'taken', 'taken@example.com', 'hash')
        );
        $repository->expects(self::never())->method('create');

        $auth = new Auth($repository, $this->memorySession());

        self::assertFalse($auth->register('taken', 'free@example.com', 'Password1!'));
        self::assertFalse($auth->isAuthenticated());
    }

    public function testGetAuthenticatedUserIdReturnsNullWhenMissing(): void
    {
        $auth = new Auth(
            $this->createMock(UserRepositoryInterface::class),
            $this->memorySession()
        );

        self::assertNull($auth->getAuthenticatedUserId());
        self::assertFalse($auth->isAuthenticated());
    }

    public function testGetAuthenticatedUserIdReturnsNullForNonNumericValue(): void
    {
        $session = $this->memorySession();
        $session->set('user_id', 'abc');
        $auth = new Auth(
            $this->createMock(UserRepositoryInterface::class),
            $session
        );

        self::assertNull($auth->getAuthenticatedUserId());
        self::assertFalse($auth->isAuthenticated());
    }

    public function testGetAuthenticatedUserIdReturnsNullForZeroOrNegative(): void
    {
        $session = $this->memorySession();
        $auth = new Auth(
            $this->createMock(UserRepositoryInterface::class),
            $session
        );

        $session->set('user_id', 0);
        self::assertNull($auth->getAuthenticatedUserId());
        self::assertFalse($auth->isAuthenticated());

        $session->set('user_id', -3);
        self::assertNull($auth->getAuthenticatedUserId());
        self::assertFalse($auth->isAuthenticated());
    }

    public function testGetAuthenticatedUserIdAcceptsNumericString(): void
    {
        $session = $this->memorySession();
        $session->set('user_id', '42');
        $auth = new Auth(
            $this->createMock(UserRepositoryInterface::class),
            $session
        );

        self::assertSame(42, $auth->getAuthenticatedUserId());
        self::assertTrue($auth->isAuthenticated());
    }

    private function repositoryReturningUser(
        string $password,
        string $compteType = 'client'
    ): UserRepositoryInterface {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(
            new User(
                1,
                'auth_test_user',
                'auth-test@example.com',
                password_hash($password, PASSWORD_DEFAULT),
                $compteType
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
