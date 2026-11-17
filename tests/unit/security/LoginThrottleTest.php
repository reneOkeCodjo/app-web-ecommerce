<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Application\Security\LoginThrottle;
use App\Infrastructure\Http\SessionStoreInterface;
use PHPUnit\Framework\TestCase;

final class LoginThrottleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    public function testBlocksAfterTheConfiguredNumberOfFailures(): void
    {
        $session = $this->memorySession();
        $throttle = new LoginThrottle($session, 3, 900);

        $throttle->recordFailure('alice');
        $throttle->recordFailure('alice');

        self::assertFalse($throttle->isBlocked('alice'));

        $throttle->recordFailure('alice');

        self::assertTrue($throttle->isBlocked('alice'));
        self::assertGreaterThan(0, $throttle->remainingSeconds('alice'));
    }

    public function testClearRemovesTheBlock(): void
    {
        $session = $this->memorySession();
        $throttle = new LoginThrottle($session, 2, 900);

        $throttle->recordFailure('alice');
        $throttle->recordFailure('alice');
        self::assertTrue($throttle->isBlocked('alice'));

        $throttle->clear('alice');

        self::assertFalse($throttle->isBlocked('alice'));
        self::assertSame(0, $throttle->remainingSeconds('alice'));
    }

    public function testUsernamesAreIndependent(): void
    {
        $session = $this->memorySession();
        $throttle = new LoginThrottle($session, 2, 900);

        $throttle->recordFailure('alice');
        $throttle->recordFailure('alice');

        self::assertTrue($throttle->isBlocked('alice'));
        self::assertFalse($throttle->isBlocked('bob'));
    }

    public function testExpiredWindowNoLongerBlocks(): void
    {
        $session = $this->memorySession();
        $throttle = new LoginThrottle($session, 1, 900);

        $throttle->recordFailure('alice');
        self::assertTrue($throttle->isBlocked('alice'));

        $raw = $session->get('_login_throttle');
        $key = array_key_first($raw);
        $raw[$key]['first'] = time() - 901;
        $session->set('_login_throttle', $raw);

        self::assertFalse($throttle->isBlocked('alice'));
        self::assertSame(0, $throttle->remainingSeconds('alice'));
    }

    public function testDifferentIpAddressesHaveSeparateCounters(): void
    {
        $session = $this->memorySession();
        $throttle = new LoginThrottle($session, 1, 900);

        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $throttle->recordFailure('alice');

        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';

        self::assertFalse($throttle->isBlocked('alice'));
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
