<?php

declare(strict_types=1);

namespace App\Shared\Module;

use App\Core\Container;
use App\Http\Router;
use App\Shared\Events\EventBus;
use InvalidArgumentException;

/**
 * Holds the enabled modules, keyed by name (O(1) lookup, duplicates caught
 * at boot) and fans each boot step out to all of them in a fixed order.
 */
final class ModuleRegistry
{
    /** @var array<string, ModuleProvider> */
    private array $modules = [];

    /** @param iterable<ModuleProvider> $modules */
    public function __construct(iterable $modules = [])
    {
        foreach ($modules as $module) {
            $this->add($module);
        }
    }

    /** @param list<class-string<ModuleProvider>> $classes */
    public static function fromClasses(array $classes): self
    {
        return new self(array_map(static fn (string $class) => new $class(), $classes));
    }

    public function add(ModuleProvider $module): void
    {
        $name = $module->name();

        if (isset($this->modules[$name])) {
            throw new InvalidArgumentException("Module [$name] is registered twice.");
        }

        $this->modules[$name] = $module;
    }

    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->modules);
    }

    public function register(Container $container): void
    {
        foreach ($this->modules as $module) {
            $module->register($container);
        }
    }

    public function routes(Router $router): void
    {
        foreach ($this->modules as $module) {
            $module->routes($router);
        }
    }

    public function listeners(EventBus $events, Container $container): void
    {
        foreach ($this->modules as $module) {
            $module->listeners($events, $container);
        }
    }

    /** @return list<string> */
    public function migrationPaths(): array
    {
        return array_values(array_filter(array_map(static fn (ModuleProvider $m) => $m->migrationsPath(), $this->modules)));
    }
}
