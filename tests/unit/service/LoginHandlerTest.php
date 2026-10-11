<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Application\Services\LoginHandler;
use App\Application\Services\Validation;
use App\Domain\Model\User;
use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LoginHandlerTest extends TestCase
{
    private Validation $validation;

    protected function setUp(): void
    {
        $this->validation = new Validation();
    }

    public function testPostRejectsInvalidPasswordWithoutCallingRepository(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::never())->method('findByUsername');

        $handler = new LoginHandler(
            $this->validation,
            $repository,
            $this->memorySession()
        );

        $html = $handler->handle('POST', [
            'user_login' => 'alice',
            'user_password' => 'short',
        ]);

        self::assertStringContainsString('feedback-error', $html);
        self::assertStringContainsString('Password must be at least 8 characters.', $html);
    }

    public function testPostLoginSuccess(): void
    {
        $handler = new LoginHandler(
            $this->validation,
            $this->repositoryReturningUser('Password1!'),
            $this->memorySession()
        );

        $html = $handler->handle('POST', [
            'user_login' => 'alice',
            'user_password' => 'Password1!',
        ]);

        self::assertStringContainsString('feedback-success', $html);
        self::assertStringContainsString('Login successful!', $html);
    }

    public function testPostLoginRejectsInvalidCredentials(): void
    {
        $handler = new LoginHandler(
            $this->validation,
            $this->repositoryReturningUser('Password1!'),
            $this->memorySession()
        );

        $html = $handler->handle('POST', [
            'user_login' => 'alice',
            'user_password' => 'WrongPass1!',
        ]);

        self::assertStringContainsString('feedback-error', $html);
        self::assertStringContainsString('Invalid username or password.', $html);
    }

    public function testPostHandlesRepositoryException(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willThrowException(
            new RuntimeException('db down')
        );

        $handler = new LoginHandler(
            $this->validation,
            $repository,
            $this->memorySession()
        );

        $html = $handler->handle('POST', [
            'user_login' => 'alice',
            'user_password' => 'Password1!',
        ]);

        self::assertStringContainsString('feedback-error', $html);
        self::assertStringContainsString(
            'An error occurred during login. Please try again later.',
            $html
        );
    }

    public function testGetShowsAlreadySignedInWhenAuthenticated(): void
    {
        $session = $this->memorySession();
        $session->set('user_id', 9);

        $handler = new LoginHandler(
            $this->validation,
            $this->createMock(UserRepositoryInterface::class),
            $session
        );

        $html = $handler->handle('GET', []);

        self::assertStringContainsString('feedback-success', $html);
        self::assertStringContainsString('You are already signed in.', $html);
    }

    public function testGetReturnsEmptyFeedbackWhenAnonymous(): void
    {
        $handler = new LoginHandler(
            $this->validation,
            $this->createMock(UserRepositoryInterface::class),
            $this->memorySession()
        );

        self::assertSame('', $handler->handle('GET', []));
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
