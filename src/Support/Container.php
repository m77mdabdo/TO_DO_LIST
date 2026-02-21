<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use InvalidArgumentException;

/**
 * A tiny service container. Every service is registered with a factory,
 * built on first use and then shared, so wiring stays in one place
 * (bootstrap/services.php) instead of being spread across the app.
 */
final class Container
{
    /** @var array<string, Closure(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @param Closure(self): mixed $factory */
    public function set(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /** Registers an object that already exists. */
    public function instance(string $id, mixed $service): void
    {
        $this->instances[$id] = $service;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $factory = $this->factories[$id] ?? throw new InvalidArgumentException("Service [$id] is not registered.");

        return $this->instances[$id] = $factory($this);
    }
}
