<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Application\DTO\RegisterRequestDTO;
use App\Application\Services\RegisterHandler;
use App\Domain\Model\User;
use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class RegisterHandlerTest extends TestCase
{
    public function testPostRegisterSuccessSignalsProductsRedirect(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(null);
        $repository->method('findByEmail')->willReturn(null);
        $repository->method('findCompteIdByType')->willReturn(1);
        $repository->method('create')->willReturn(
            new User(3, 'alice', 'alice@example.com', 'hash')
        );

        $handler = new RegisterHandler($repository, $this->memorySession());

        $result = $handler->handle(
            'POST',
            new RegisterRequestDTO('alice', 'alice@example.com', 'Password1!')
        );

        self::assertNull($result);
    }

    public function testPostRegisterRejectsTakenAccount(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(
            new User(1, 'alice', 'alice@example.com', 'hash')
        );

        $handler = new RegisterHandler($repository, $this->memorySession());

        $html = $handler->handle(
            'POST',
            new RegisterRequestDTO('alice', 'alice@example.com', 'Password1!')
        );

        self::assertIsString($html);
        self::assertStringContainsString('feedback-error', $html);
        self::assertStringContainsString('Username or email is already taken.', $html);
    }

    public function testGetRedirectsWhenAuthenticated(): void
    {
        $session = $this->memorySession();
        $session->set('user_id', 4);

        $handler = new RegisterHandler(
            $this->createMock(UserRepositoryInterface::class),
            $session
        );

        self::assertNull($handler->handle('GET'));
    }

    private function memorySession(): SessionStoreInterface
    {
        return new class implements SessionStoreInterface {
            /** @var array<string, mixed> */
            private array $values = [];
            private bool $regenerated = false;

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
            }
        };
    }
}
