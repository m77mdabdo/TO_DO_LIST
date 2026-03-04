<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * A minimal dependency injection container.
 *
 * - singleton() registers how to build a shared service.
 * - get() returns it, or builds any class by reading its constructor
 *   and resolving each type-hinted argument (auto-wiring).
 */
final class Container
{
    /** @var array<string, Closure(self): object> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @param Closure(self): object $factory */
    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function instance(string $id, object $service): void
    {
        $this->instances[$id] = $service;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }

        return $this->build($id);
    }

    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Cannot resolve [$class]: no binding and no such class.");
        }

        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Cannot resolve [$class]: bind an implementation for it.");
        }

        $arguments = [];

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $arguments[] = $this->get($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } else {
                throw new RuntimeException("Cannot resolve parameter \${$parameter->getName()} of [$class].");
            }
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
