<?php

namespace App\Infrastructure\Http;

interface SessionStoreInterface
{
    /**
     * Store a value in the session.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Read a session value, or null if missing.
     */
    public function get(string $key): mixed;

    /**
     * Remove a session key.
     */
    public function remove(string $key): void;

    /**
     * Regenerate the session id (call after login).
     */
    public function regenerate(): void;

    /**
     * Destroy the whole session (logout).
     */
    public function destroy(): void;
}
