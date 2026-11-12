<?php

declare(strict_types=1);

namespace App\Modules\Activity;

use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Activity\Http\ActivityController;
use App\Modules\Activity\Application\ActivityRecorder;
use App\Modules\Activity\Contract\ActivityFeed;
use App\Modules\Activity\Domain\ActivityLog;
use App\Modules\Activity\Infrastructure\PdoActivityLog;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use PDO;

/**
 * The board and card activity log, filled only from other modules' events.
 * Offers: Contract\ActivityFeed.
 */
final class ActivityModule implements ModuleProvider
{
    public function name(): string
    {
        return 'activity';
    }

    public function register(Container $container): void
    {
        $container->singleton(ActivityLog::class, fn (Container $c) => new PdoActivityLog($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(ActivityFeed::class, fn (Container $c) => $c->get(ActivityLog::class));
        $container->singleton(ActivityRecorder::class, fn (Container $c) => $c->build(ActivityRecorder::class));
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->get('/boards/{board:ulid}/activity', [ActivityController::class, 'board']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
        // The recorder (and its database work) is only built when the first event arrives.
        foreach (ActivityRecorder::events() as $name) {
            $events->listen($name, static fn ($event) => $container->get(ActivityRecorder::class)->record($event));
        }
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
