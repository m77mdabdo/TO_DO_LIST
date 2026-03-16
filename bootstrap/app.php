<?php

declare(strict_types=1);

/*
 | Wires the application together and returns the container.
 | Swapping an implementation (another database, a fake clock, an
 | in-memory session) only needs a change here, nowhere else.
 */

use App\Auth\LoginThrottle;
use App\Auth\PdoLoginThrottle;
use App\Auth\PdoUserRepository;
use App\Auth\UserRepository;
use App\Core\Config;
use App\Core\Container;
use App\Database\ConnectionFactory;
use App\Database\Migrator;
use App\Http\Kernel;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Router;
use App\Http\Url;
use App\Security\Csrf;
use App\Session\Session;
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

return static function (Config $config, Session $session, string $basePath = ''): Container {
    $root = dirname(__DIR__);
    $container = new Container();

    date_default_timezone_set((string) $config->get('timezone', 'UTC'));

    $container->instance(Config::class, $config);
    $container->instance(Session::class, $session);
    $container->singleton(Clock::class, fn () => new SystemClock());

    $container->singleton(PDO::class, function () use ($config, $root): PDO {
        $pdo = ConnectionFactory::make((array) $config->get('db'));
        (new Migrator($pdo, $root . '/database'))->migrate();

        return $pdo;
    });

    $container->singleton(TaskRepository::class, fn (Container $c) => new PdoTaskRepository($c->get(PDO::class), $c->get(Clock::class)));
    $container->singleton(UserRepository::class, fn (Container $c) => new PdoUserRepository($c->get(PDO::class), $c->get(Clock::class)));
    $container->singleton(LoginThrottle::class, fn (Container $c) => new PdoLoginThrottle($c->get(PDO::class), $c->get(Clock::class)));

    $container->singleton(Url::class, fn () => new Url($basePath, $root));
    $container->singleton(Csrf::class, fn (Container $c) => new Csrf($c->get(Session::class)));
    $container->singleton(FormState::class, fn (Container $c) => new FormState($c->get(Session::class)));

    $container->singleton(View::class, function (Container $c) use ($config, $root): View {
        $view = new View($root . '/views', $c->get(Url::class), $c->get(Csrf::class), $c->get(Session::class), $c->get(FormState::class));
        $view->share('appName', (string) $config->get('app_name', 'Todo Board'));
        $view->share('today', $c->get(Clock::class)->now());
        $view->share('timeAgo', $c->get(TimeAgo::class));

        return $view;
    });

    $container->singleton(Router::class, function () use ($root): Router {
        $router = new Router();
        (require $root . '/routes/web.php')($router);

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

    return $container;
};
