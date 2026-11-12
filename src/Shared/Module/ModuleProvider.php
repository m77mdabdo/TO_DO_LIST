<?php

declare(strict_types=1);

namespace App\Shared\Module;

use App\Core\Container;
use App\Http\Router;
use App\Shared\Events\EventBus;

/**
 * The single entry point of a module. The app knows a module only through
 * its provider: what services it binds, which URLs it answers, which
 * events it reacts to and where its migrations live.
 *
 * Modules never reach into each other's internals. They depend on another
 * module's Contract\ namespace or react to its Domain Events, nothing else.
 */
interface ModuleProvider
{
    /** Short, unique, lowercase, e.g. "identity". */
    public function name(): string;

    /** Bind the module's services. Called once while the app boots. */
    public function register(Container $container): void;

    public function routes(Router $router): void;

    /**
     * Subscribe to events. Resolve services inside the listener, not here,
     * so nothing is built until an event actually fires.
     */
    public function listeners(EventBus $events, Container $container): void;

    /** Folder of <id>.<driver>.sql files, or null when the module has no tables. */
    public function migrationsPath(): ?string;
}
