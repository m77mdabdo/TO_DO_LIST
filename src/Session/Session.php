<?php

declare(strict_types=1);

namespace App\Session;

/**
 * Key/value storage that lives across requests for one visitor.
 *
 * Flash values are kept for the next request only and removed once read.
 */
interface Session
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function forget(string $key): void;

    public function flash(string $key, mixed $value): void;

    /** Reads a flash value and removes it. */
    public function pull(string $key, mixed $default = null): mixed;

    /** New session id, same data. Call it after logging in. */
    public function regenerate(): void;

    /** Drops all data and starts over with a new id. */
    public function invalidate(): void;
}
