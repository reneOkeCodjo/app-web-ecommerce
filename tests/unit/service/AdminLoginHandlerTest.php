<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Application\DTO\LoginRequestDTO;
use App\Application\Services\Auth\AdminLoginHandler;
use App\Domain\Model\User;
use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class AdminLoginHandlerTest extends TestCase
{
    public function testPostAdminLoginSuccessSignalsDashboardRedirect(): void
    {
        $handler = new AdminLoginHandler(
            $this->repositoryReturningUser('Password1!', 'admin'),
            $this->memorySession()
        );

        $result = $handler->handle(
            'POST',
            new LoginRequestDTO('admin_user', 'Password1!')
        );

        self::assertNull($result);
    }

    public function testPostRejectsNonAdminCredentials(): void
    {
        $session = $this->memorySession();
        $handler = new AdminLoginHandler(
            $this->repositoryReturningUser('Password1!', 'client'),
            $session
        );

        $html = $handler->handle(
            'POST',
            new LoginRequestDTO('client_user', 'Password1!')
        );

        self::assertIsString($html);
        self::assertStringContainsString('feedback-error', $html);
        self::assertStringContainsString('Admin access required.', $html);
        self::assertNull($session->get('user_id'));
        self::assertNull($session->get('compte_type'));
    }

    public function testPostRejectsInvalidCredentials(): void
    {
        $handler = new AdminLoginHandler(
            $this->repositoryReturningUser('Password1!', 'admin'),
            $this->memorySession()
        );

        $html = $handler->handle(
            'POST',
            new LoginRequestDTO('admin_user', 'WrongPass1!')
        );

        self::assertIsString($html);
        self::assertStringContainsString('Invalid username or password.', $html);
    }

    public function testGetRedirectsWhenAdminAuthenticated(): void
    {
        $session = $this->memorySession();
        $session->set('user_id', 9);
        $session->set('compte_type', 'admin');

        $handler = new AdminLoginHandler(
            $this->createMock(UserRepositoryInterface::class),
            $session
        );

        self::assertNull($handler->handle('GET'));
    }

    public function testGetReturnsEmptyFeedbackWhenAnonymous(): void
    {
        $handler = new AdminLoginHandler(
            $this->createMock(UserRepositoryInterface::class),
            $this->memorySession()
        );

        self::assertSame('', $handler->handle('GET'));
    }

    private function repositoryReturningUser(
        string $password,
        string $compteType
    ): UserRepositoryInterface {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(
            new User(
                1,
                'admin_user',
                'admin@example.com',
                password_hash($password, PASSWORD_DEFAULT),
                $compteType
            )
        );

        return $repository;
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
