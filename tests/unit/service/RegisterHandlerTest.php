<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Application\Services\RegisterHandler;
use App\Application\Services\Validation;
use App\Domain\Model\User;
use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class RegisterHandlerTest extends TestCase
{
    private Validation $validation;

    protected function setUp(): void
    {
        $this->validation = new Validation();
    }

    public function testPostRejectsInvalidPayloadWithoutRepositoryCreate(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::never())->method('create');

        $handler = new RegisterHandler(
            $this->validation,
            $repository,
            $this->memorySession()
        );

        $html = $handler->handle('POST', [
            'user_login' => 'alice',
            'user_mail' => 'bad',
            'user_password' => 'Password1!',
            'password_confirm' => 'Password1!',
        ]);

        self::assertStringContainsString('feedback-error', $html);
        self::assertStringContainsString('Email format is invalid.', $html);
    }

    public function testPostRegisterSuccess(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(null);
        $repository->method('findByEmail')->willReturn(null);
        $repository->method('findCompteIdByType')->willReturn(1);
        $repository->method('create')->willReturn(
            new User(3, 'alice', 'alice@example.com', 'hash')
        );

        $handler = new RegisterHandler(
            $this->validation,
            $repository,
            $this->memorySession()
        );

        $html = $handler->handle('POST', [
            'user_login' => 'alice',
            'user_mail' => 'alice@example.com',
            'user_password' => 'Password1!',
            'password_confirm' => 'Password1!',
        ]);

        self::assertStringContainsString('feedback-success', $html);
        self::assertStringContainsString('Account created successfully!', $html);
    }

    public function testPostRegisterRejectsTakenAccount(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(
            new User(1, 'alice', 'alice@example.com', 'hash')
        );

        $handler = new RegisterHandler(
            $this->validation,
            $repository,
            $this->memorySession()
        );

        $html = $handler->handle('POST', [
            'user_login' => 'alice',
            'user_mail' => 'alice@example.com',
            'user_password' => 'Password1!',
            'password_confirm' => 'Password1!',
        ]);

        self::assertStringContainsString('feedback-error', $html);
        self::assertStringContainsString('Username or email is already taken.', $html);
    }

    public function testGetShowsAlreadySignedInWhenAuthenticated(): void
    {
        $session = $this->memorySession();
        $session->set('user_id', 4);

        $handler = new RegisterHandler(
            $this->validation,
            $this->createMock(UserRepositoryInterface::class),
            $session
        );

        $html = $handler->handle('GET', []);

        self::assertStringContainsString('You are already signed in.', $html);
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
