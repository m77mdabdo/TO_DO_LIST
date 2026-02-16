<?php

declare(strict_types=1);

namespace App\Session;

interface Session
{
    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value): void;

    public function forget(string $key): void;

    /** Store a value for the next request only. */
    public function flash(string $key, mixed $value): void;

    /** Read (and remove) a value flashed by the previous request. */
    public function pullFlash(string $key, mixed $default = null): mixed;

    /** New session id, same data. Call after logging in. */
    public function regenerate(): void;

    /** Drop all data and the cookie. Call when logging out. */
    public function invalidate(): void;
}
