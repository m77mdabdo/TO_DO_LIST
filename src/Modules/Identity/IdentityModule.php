<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Router;
use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Contract\Authenticator;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Identity\Domain\LoginThrottle;
use App\Modules\Identity\Domain\UserRepository;
use App\Modules\Identity\Http\LoginController;
use App\Modules\Identity\Http\RegisterController;
use App\Modules\Identity\Infrastructure\PdoLoginThrottle;
use App\Modules\Identity\Infrastructure\PdoUserRepository;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use PDO;

/**
 * Accounts, sign up, log in, log out and login throttling.
 * Publishes: identity.user_registered, identity.user_logged_in.
 * Offers: Contract\Authenticator, Contract\UserDirectory.
 */
final class IdentityModule implements ModuleProvider
{
    public function name(): string
    {
        return 'identity';
    }

    public function register(Container $container): void
    {
        $container->singleton(PdoUserRepository::class, fn (Container $c) => new PdoUserRepository($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(UserRepository::class, fn (Container $c) => $c->get(PdoUserRepository::class));
        $container->singleton(UserDirectory::class, fn (Container $c) => $c->get(PdoUserRepository::class));
        $container->singleton(LoginThrottle::class, fn (Container $c) => new PdoLoginThrottle($c->get(PDO::class), $c->get(Clock::class)));

        // One instance behind both names, so the "current user" is looked up once per request.
        $container->singleton(AuthService::class, fn (Container $c) => $c->build(AuthService::class));
        $container->singleton(Authenticator::class, fn (Container $c) => $c->get(AuthService::class));
    }

    public function routes(Router $router): void
    {
        $router->group([RedirectIfAuthenticated::class], function (Router $router): void {
            $router->get('/login', [LoginController::class, 'show']);
            $router->post('/login', [LoginController::class, 'store']);
            $router->get('/register', [RegisterController::class, 'show']);
            $router->post('/register', [RegisterController::class, 'store']);
        });

        $router->group([Authenticate::class], function (Router $router): void {
            $router->post('/logout', [LoginController::class, 'destroy']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
    }

    public function migrationsPath(): ?string
    {
        // users and login_attempts live in the core baseline migration.
        return null;
    }
}
