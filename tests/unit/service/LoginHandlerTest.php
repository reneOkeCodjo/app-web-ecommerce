<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Application\DTO\LoginRequestDTO;
use App\Application\Services\LoginHandler;
use App\Domain\Model\User;
use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LoginHandlerTest extends TestCase
{
    public function testPostLoginSuccessSignalsProductsRedirect(): void
    {
        $handler = new LoginHandler(
            $this->repositoryReturningUser('Password1!'),
            $this->memorySession()
        );

        $result = $handler->handle(
            'POST',
            new LoginRequestDTO('alice', 'Password1!')
        );

        self::assertNull($result);
    }

    public function testPostLoginRejectsInvalidCredentials(): void
    {
        $handler = new LoginHandler(
            $this->repositoryReturningUser('Password1!'),
            $this->memorySession()
        );

        $html = $handler->handle(
            'POST',
            new LoginRequestDTO('alice', 'WrongPass1!')
        );

        self::assertIsString($html);
        self::assertStringContainsString('feedback-error', $html);
        self::assertStringContainsString('Invalid username or password.', $html);
    }

    public function testPostHandlesRepositoryException(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willThrowException(
            new RuntimeException('db down')
        );

        $handler = new LoginHandler($repository, $this->memorySession());

        $html = $handler->handle(
            'POST',
            new LoginRequestDTO('alice', 'Password1!')
        );

        self::assertIsString($html);
        self::assertStringContainsString('feedback-error', $html);
        self::assertStringContainsString(
            'An error occurred during login. Please try again later.',
            $html
        );
    }

    public function testGetRedirectsWhenAuthenticated(): void
    {
        $session = $this->memorySession();
        $session->set('user_id', 9);

        $handler = new LoginHandler(
            $this->createMock(UserRepositoryInterface::class),
            $session
        );

        self::assertNull($handler->handle('GET'));
    }

    public function testGetReturnsEmptyFeedbackWhenAnonymous(): void
    {
        $handler = new LoginHandler(
            $this->createMock(UserRepositoryInterface::class),
            $this->memorySession()
        );

        self::assertSame('', $handler->handle('GET'));
    }

    private function repositoryReturningUser(string $password): UserRepositoryInterface
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(
            new User(
                1,
                'alice',
                'alice@example.com',
                password_hash($password, PASSWORD_DEFAULT)
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
