<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Infrastructure\Http\NativeSessionStore;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcess]
#[PreserveGlobalState(false)]
final class NativeSessionStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        session_save_path(sys_get_temp_dir());
        session_name('phpunit_session_' . bin2hex(random_bytes(4)));
        unset($_SERVER['HTTPS']);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        parent::tearDown();
    }

    public function testSetGetAndRemove(): void
    {
        $session = new NativeSessionStore();

        $session->set('user_id', 7);
        self::assertSame(7, $session->get('user_id'));

        $session->remove('user_id');
        self::assertNull($session->get('user_id'));
    }

    public function testRegenerateChangesSessionId(): void
    {
        $session = new NativeSessionStore();
        $session->set('flag', true);
        $before = session_id();

        $session->regenerate();

        self::assertNotSame('', $before);
        self::assertNotSame($before, session_id());
        self::assertTrue($session->get('flag'));
    }

    public function testDestroyClearsSessionData(): void
    {
        $session = new NativeSessionStore();
        $session->set('user_id', 1);

        $session->destroy();

        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testSecureCookieFlagsWithoutHttps(): void
    {
        $_SERVER['HTTPS'] = 'off';
        new NativeSessionStore();
        $params = session_get_cookie_params();

        self::assertSame('1', ini_get('session.use_only_cookies'));
        self::assertTrue($params['httponly']);
        self::assertSame('Lax', $params['samesite']);
        self::assertFalse($params['secure']);
    }

    public function testSecureCookieFlagWithHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
        new NativeSessionStore();
        $params = session_get_cookie_params();

        self::assertTrue($params['secure']);
        self::assertTrue($params['httponly']);
        self::assertSame('Lax', $params['samesite']);
    }
}
