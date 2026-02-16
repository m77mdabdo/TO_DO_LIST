<?php

declare(strict_types=1);

namespace App\Session;

/**
 * Keeps session data in memory. Used by the tests and the seed script.
 */
final class ArraySession implements Session
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, mixed> */
    private array $flash = [];

    public int $regenerated = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $this->flash[$key] = $value;
    }

    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $this->flash[$key] ?? $default;
        unset($this->flash[$key]);

        return $value;
    }

    public function regenerate(): void
    {
        $this->regenerated++;
    }

    public function invalidate(): void
    {
        $this->data = $this->flash = [];
    }
}
