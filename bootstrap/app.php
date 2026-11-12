<?php

declare(strict_types=1);

/*
 | Wires the application together and returns the container.
 | Swapping an implementation (another database, a fake clock, an
 | in-memory session) only needs a change here, nowhere else.
 */

use App\Core\Config;
use App\Core\Container;
use App\Database\ConnectionFactory;
use App\Database\Migrations\MigrationRunner;
use App\Http\Kernel;
use App\I18n\Translator;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Router;
use App\Http\Url;
use App\Security\Csrf;
use App\Session\Session;
use App\Shared\Events\EventBus;
use App\Shared\Events\SyncEventBus;
use App\Shared\Module\ModuleRegistry;
use App\Support\Clock;
use App\Support\FileLogger;
use App\Support\Logger;
use App\Support\SystemClock;
use App\Support\TimeAgo;
use App\Task\PdoTaskRepository;
use App\Task\TaskRepository;
use App\View\FormState;
use App\View\View;

require_once __DIR__ . '/autoload.php';

/*
 | $autoMigrate: apply pending migrations on the first database use.
 | Handy for local SQLite; turn it off and run database/migrate.php in production.
 */
return static function (Config $config, Session $session, string $basePath = '', ?bool $autoMigrate = null): Container {
    $root = dirname(__DIR__);
    $container = new Container();

    date_default_timezone_set((string) $config->get('timezone', 'UTC'));

    $container->instance(Config::class, $config);
    $container->instance(Session::class, $session);
    $container->singleton(Clock::class, fn () => new SystemClock());

    $modules = ModuleRegistry::fromClasses(require __DIR__ . '/modules.php');
    $container->instance(ModuleRegistry::class, $modules);

    $autoMigrate ??= (bool) $config->get('auto_migrate', true);
    $migrationDirs = [$root . '/database/migrations', ...$modules->migrationPaths()];

    $container->singleton(MigrationRunner::class, fn (Container $c) => new MigrationRunner($c->get(PDO::class), $migrationDirs));
    $container->singleton(PDO::class, function (Container $c) use ($config, $autoMigrate, $migrationDirs): PDO {
        $pdo = ConnectionFactory::make((array) $config->get('db'));

        if ($autoMigrate) {
            (new MigrationRunner($pdo, $migrationDirs))->migrate();
        }

        return $pdo;
    });

    $container->singleton(TaskRepository::class, fn (Container $c) => new PdoTaskRepository($c->get(PDO::class), $c->get(Clock::class)));

    $container->singleton(Translator::class, fn (Container $c) => new Translator(
        $root . '/lang',
        (string) $config->get('locale', 'ar'),
        $c->get(Session::class),
    ));
    $container->singleton(Url::class, fn () => new Url($basePath, $root));
    $container->singleton(Csrf::class, fn (Container $c) => new Csrf($c->get(Session::class)));
    $container->singleton(FormState::class, fn (Container $c) => new FormState($c->get(Session::class)));

    $container->singleton(View::class, function (Container $c) use ($config, $root): View {
        $view = new View($root . '/views', $c->get(Url::class), $c->get(Csrf::class), $c->get(Session::class), $c->get(FormState::class), $c->get(Translator::class));
        $view->share('appName', (string) $config->get('app_name', 'Todo Board'));
        $view->share('today', $c->get(Clock::class)->now());
        $view->share('timeAgo', $c->get(TimeAgo::class));

        return $view;
    });

    $container->singleton(EventBus::class, function (Container $c) use ($modules): EventBus {
        $events = new SyncEventBus();
        $modules->listeners($events, $c);

        return $events;
    });

    $container->singleton(Router::class, function () use ($root, $modules): Router {
        $router = new Router();
        (require $root . '/routes/web.php')($router);
        $modules->routes($router);

        return $router;
    });

    $container->singleton(Logger::class, fn (Container $c) => new FileLogger($root . '/storage/logs', $c->get(Clock::class)));

    $container->singleton(Kernel::class, fn (Container $c) => new Kernel(
        $c,
        $c->get(Router::class),
        [SecurityHeaders::class, VerifyCsrfToken::class],
        $c->get(Logger::class),
        (bool) $config->get('debug', false),
    ));

    // Modules bind last, so they can build on the shared services above.
    $modules->register($container);

    return $container;
};
