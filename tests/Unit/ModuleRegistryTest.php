<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use App\Http\Router;
use App\Shared\Events\EventBus;
use App\Shared\Events\SyncEventBus;
use App\Shared\Module\ModuleProvider;
use App\Shared\Module\ModuleRegistry;
use InvalidArgumentException;
use Tests\TestCase;

final class FakeModule implements ModuleProvider
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(private string $name = 'fake', private ?string $migrations = null)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function register(Container $container): void
    {
        $this->calls[] = 'register';
    }

    public function routes(Router $router): void
    {
        $this->calls[] = 'routes';
    }

    public function listeners(EventBus $events, Container $container): void
    {
        $this->calls[] = 'listeners';
    }

    public function migrationsPath(): ?string
    {
        return $this->migrations;
    }
}

final class ModuleRegistryTest extends TestCase
{
    public function testFansEveryBootStepOutToEachModule(): void
    {
        $module = new FakeModule();
        $registry = new ModuleRegistry([$module]);

        $registry->register(new Container());
        $registry->routes(new Router());
        $registry->listeners(new SyncEventBus(), new Container());

        $this->assertSame(['register', 'routes', 'listeners'], $module->calls);
    }

    public function testCollectsOnlyExistingMigrationFolders(): void
    {
        $registry = new ModuleRegistry([new FakeModule('a', '/a/migrations'), new FakeModule('b')]);

        $this->assertSame(['/a/migrations'], $registry->migrationPaths());
    }

    public function testRejectsDuplicateNames(): void
    {
        $this->assertThrows(InvalidArgumentException::class, fn () => new ModuleRegistry([new FakeModule(), new FakeModule()]));
    }
}
