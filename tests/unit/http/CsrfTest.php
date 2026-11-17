<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\SessionStoreInterface;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    public function testTokenIsCreatedOnceAndReused(): void
    {
        $session = $this->memorySession();

        $first = Csrf::token($session);
        $second = Csrf::token($session);

        self::assertSame(64, strlen($first));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
        self::assertSame($first, $second);
    }

    public function testFieldContainsEscapedHiddenInput(): void
    {
        $session = $this->memorySession();
        $token = Csrf::token($session);

        $html = Csrf::field($session);

        self::assertStringContainsString('type="hidden"', $html);
        self::assertStringContainsString('name="_csrf"', $html);
        self::assertStringContainsString(htmlspecialchars($token, ENT_QUOTES, 'UTF-8'), $html);
    }

    public function testValidateAcceptsOnlyTheStoredToken(): void
    {
        $session = $this->memorySession();
        $token = Csrf::token($session);

        self::assertTrue(Csrf::validate($session, $token));
        self::assertFalse(Csrf::validate($session, $token . 'x'));
        self::assertFalse(Csrf::validate($session, ''));
        self::assertFalse(Csrf::validate($session, null));
    }

    public function testValidateRejectsMissingSessionToken(): void
    {
        $session = $this->memorySession();

        self::assertFalse(Csrf::validate($session, 'anything'));
    }

    public function testRotateReplacesTheToken(): void
    {
        $session = $this->memorySession();
        $before = Csrf::token($session);

        Csrf::rotate($session);

        $after = Csrf::token($session);

        self::assertNotSame($before, $after);
        self::assertFalse(Csrf::validate($session, $before));
        self::assertTrue(Csrf::validate($session, $after));
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
