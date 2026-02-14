<?php

declare(strict_types=1);

namespace App\Session;

/**
 * A session that only lives in memory, used by the tests.
 */
final class ArraySession implements Session
{
    private const FLASH = '_flash';

    /** @param array<string, mixed> $data */
    public function __construct(private array $data = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $this->data[self::FLASH][$key] = $value;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->data[self::FLASH][$key] ?? $default;
        unset($this->data[self::FLASH][$key]);

        return $value;
    }

    public function regenerate(): void
    {
    }

    public function invalidate(): void
    {
        $this->data = [];
    }
}
