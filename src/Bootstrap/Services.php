<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Auth\Authenticator;
use App\Auth\AuthService;
use App\Auth\LoginThrottle;
use App\Auth\PdoLoginThrottle;
use App\Auth\PdoUserRepository;
use App\Auth\UserRepository;
use App\Controllers\AuthController;
use App\Controllers\BoardController;
use App\Controllers\TaskController;
use App\Database\ConnectionFactory;
use App\Database\Migrator;
use App\Http\FormState;
use App\Http\Kernel;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\HandleErrors;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Redirector;
use App\Http\Routing\Router;
use App\Http\UrlGenerator;
use App\Security\Csrf;
use App\Session\NativeSession;
use App\Session\Session;
use App\Support\Clock;
use App\Support\Container;
use App\Support\SystemClock;
use App\Task\PdoTaskRepository;
use App\Task\TaskRepository;
use App\Task\TaskService;
use App\Validation\Validator;
use App\View\View;
use App\View\ViewHelpers;
use PDO;

/**
 * The one place that decides which class backs each service.
 * Swap an implementation here (say, another TaskRepository) and
 * nothing else in the app has to change.
 */
final class Services
{
    /**
     * @param array<string, mixed> $config contents of config/app.php
     * @param string               $root   the project folder
     * @param string               $basePath URL prefix when the app lives in a sub folder
     */
    public static function register(Container $c, array $config, string $root, string $basePath = ''): void
    {
        $c->instance('config', $config);

        self::infrastructure($c, $config, $root, $basePath);
        self::domain($c);
        self::http($c, $config, $root);
    }

    /** @param array<string, mixed> $config */
    private static function infrastructure(Container $c, array $config, string $root, string $basePath): void
    {
        $c->set(Clock::class, fn () => new SystemClock());

        $c->set(PDO::class, function () use ($config, $root) {
            $pdo = ConnectionFactory::make($config['db']);
            (new Migrator($pdo, $root . '/database'))->migrate();

            return $pdo;
        });

        $c->set(NativeSession::class, fn () => new NativeSession());
        $c->set(Session::class, fn (Container $c) => $c->get(NativeSession::class));
        $c->set(Csrf::class, fn (Container $c) => new Csrf($c->get(Session::class)));
        $c->set(FormState::class, fn (Container $c) => new FormState($c->get(Session::class)));
        $c->set(UrlGenerator::class, fn () => new UrlGenerator($basePath, $root . '/public'));
        $c->set(Validator::class, fn () => new Validator());
    }

    private static function domain(Container $c): void
    {
        $c->set(TaskRepository::class, fn (Container $c) => new PdoTaskRepository($c->get(PDO::class), $c->get(Clock::class)));
        $c->set(TaskService::class, fn (Container $c) => new TaskService(
            $c->get(TaskRepository::class),
            $c->get(Validator::class),
            $c->get(Clock::class),
        ));

        $c->set(UserRepository::class, fn (Container $c) => new PdoUserRepository($c->get(PDO::class), $c->get(Clock::class)));
        $c->set(LoginThrottle::class, fn (Container $c) => new PdoLoginThrottle($c->get(PDO::class), $c->get(Clock::class)));
        $c->set(Authenticator::class, fn (Container $c) => new Authenticator(
            $c->get(Session::class),
            $c->get(UserRepository::class),
            $c->get(Csrf::class),
        ));
        $c->set(AuthService::class, fn (Container $c) => new AuthService(
            $c->get(UserRepository::class),
            $c->get(Authenticator::class),
            $c->get(LoginThrottle::class),
            $c->get(Validator::class),
        ));
    }

    /** @param array<string, mixed> $config */
    private static function http(Container $c, array $config, string $root): void
    {
        $c->set(Redirector::class, fn (Container $c) => new Redirector(
            $c->get(UrlGenerator::class),
            $c->get(Session::class),
            $c->get(FormState::class),
        ));

        $c->set(View::class, function (Container $c) use ($config, $root) {
            $view = new View($root . '/views');
            $view->share('appName', $config['app_name']);
            $view->share('v', new ViewHelpers(
                $c->get(UrlGenerator::class),
                $c->get(Csrf::class),
                $c->get(FormState::class),
                $c->get(Session::class),
                $c->get(Clock::class),
            ));

            return $view;
        });

        // middleware
        $c->set(SecurityHeaders::class, fn () => new SecurityHeaders());
        $c->set(HandleErrors::class, fn (Container $c) => new HandleErrors($c->get(View::class), (bool) $config['debug']));
        $c->set(VerifyCsrfToken::class, fn (Container $c) => new VerifyCsrfToken($c->get(Csrf::class)));
        $c->set(Authenticate::class, fn (Container $c) => new Authenticate($c->get(Authenticator::class), $c->get(Redirector::class)));
        $c->set(RedirectIfAuthenticated::class, fn (Container $c) => new RedirectIfAuthenticated(
            $c->get(Authenticator::class),
            $c->get(Redirector::class),
        ));

        // controllers
        $c->set(AuthController::class, fn (Container $c) => new AuthController(
            $c->get(AuthService::class),
            $c->get(Authenticator::class),
            $c->get(View::class),
            $c->get(Redirector::class),
        ));
        $c->set(BoardController::class, fn (Container $c) => new BoardController(
            $c->get(TaskService::class),
            $c->get(View::class),
            $c->get(Clock::class),
        ));
        $c->set(TaskController::class, fn (Container $c) => new TaskController(
            $c->get(TaskService::class),
            $c->get(View::class),
            $c->get(Redirector::class),
        ));

        $c->set(Router::class, function () use ($root) {
            $router = new Router();
            (require $root . '/config/routes.php')($router);

            return $router;
        });

        $c->set(Kernel::class, fn (Container $c) => new Kernel($c, $c->get(Router::class), [
            SecurityHeaders::class,
            HandleErrors::class,
            VerifyCsrfToken::class,
        ]));
    }
}
