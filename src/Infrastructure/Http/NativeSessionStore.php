<?php

namespace App\Infrastructure\Http;

/**
 * Session store backed by PHP session_start() / $_SESSION.
 * Applies cookie and session security options before the session starts.
 */
class NativeSessionStore implements SessionStoreInterface
{
    /** Browser session cookie (expires when the browser closes). */
    private const COOKIE_LIFETIME = 0;

    private const COOKIE_PATH = '/';

    private const COOKIE_SAMESITE = 'Lax';

    public function __construct()
    {
        $this->ensureStarted();
    }

    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    public function get(string $key): mixed
    {
        $this->ensureStarted();

        return $_SESSION[$key] ?? null;
    }

    public function remove(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->ensureStarted();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->ensureStarted();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => (bool) $params['secure'],
                    'httponly' => (bool) $params['httponly'],
                    'samesite' => $params['samesite'] ?? self::COOKIE_SAMESITE,
                ]
            );
        }

        session_destroy();
    }

    private function ensureStarted(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $this->configureSecureSession();

        session_start([
            'cookie_lifetime' => self::COOKIE_LIFETIME,
            'cookie_path' => self::COOKIE_PATH,
            'cookie_secure' => $this->isHttps(),
            'cookie_httponly' => true,
            'cookie_samesite' => self::COOKIE_SAMESITE,
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'use_trans_sid' => false,
        ]);
    }

    /**
     * Security defaults that must be set before session_start().
     */
    private function configureSecureSession(): void
    {
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', self::COOKIE_SAMESITE);
        ini_set('session.cookie_secure', $this->isHttps() ? '1' : '0');

        session_set_cookie_params([
            'lifetime' => self::COOKIE_LIFETIME,
            'path' => self::COOKIE_PATH,
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => self::COOKIE_SAMESITE,
        ]);
    }

    private function isHttps(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
