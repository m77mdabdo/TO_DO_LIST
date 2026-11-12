<?php

declare(strict_types=1);

namespace App\Modules\LegacyImport;

use App\Core\Container;
use App\Http\Router;
use App\Modules\Identity\Contract\Events\UserLoggedIn;
use App\Modules\LegacyImport\Application\LegacyImporter;
use App\Modules\LegacyImport\Domain\LegacyStore;
use App\Modules\LegacyImport\Infrastructure\PdoLegacyStore;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use App\Support\Logger;
use PDO;
use RuntimeException;

/**
 * One-way import of the old single-board tasks into boards and cards.
 * Runs for a user when they log in (one indexed query when there's nothing
 * left), or for everyone with `php database/import-legacy.php`.
 * Listens to: identity.user_logged_in.
 */
final class LegacyImportModule implements ModuleProvider
{
    public function name(): string
    {
        return 'legacy-import';
    }

    public function register(Container $container): void
    {
        $container->singleton(LegacyStore::class, fn (Container $c) => new PdoLegacyStore($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(LegacyImporter::class, fn (Container $c) => $c->build(LegacyImporter::class));
    }

    public function routes(Router $router): void
    {
    }

    public function listeners(EventBus $events, Container $container): void
    {
        $events->listen(UserLoggedIn::name(), static function (UserLoggedIn $event) use ($container): void {
            $report = $container->get(LegacyImporter::class)->importFor($event->userId, $event->name);

            // A failed import never blocks the login; it is logged and retried on the next one.
            foreach ($report->problems as $problem) {
                $container->get(Logger::class)->error(new RuntimeException('Legacy import: ' . $problem));
            }
        });
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
