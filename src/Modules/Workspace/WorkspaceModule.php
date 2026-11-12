<?php

declare(strict_types=1);

namespace App\Modules\Workspace;

use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Identity\Contract\Authenticator;
use App\Modules\Identity\Contract\Events\UserLoggedIn;
use App\Modules\Identity\Contract\Events\UserRegistered;
use App\Modules\Workspace\Application\WorkspaceService;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceMembership;
use App\Modules\Workspace\Contract\WorkspaceProvisioning;
use App\Modules\Workspace\Domain\WorkspaceRepository;
use App\Modules\Workspace\Http\WorkspaceController;
use App\Modules\Workspace\Infrastructure\PdoWorkspaceRepository;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use App\View\View;
use PDO;

/**
 * Companies, their workspaces and workspace membership.
 * Listens to: identity.user_registered, identity.user_logged_in (gives every user a workspace).
 * Publishes: workspace.created, workspace.member_joined, workspace.member_role_changed, workspace.member_removed.
 * Offers: Contract\WorkspaceDirectory, Contract\WorkspaceProvisioning, Contract\WorkspaceMembership.
 */
final class WorkspaceModule implements ModuleProvider
{
    public function name(): string
    {
        return 'workspace';
    }

    public function register(Container $container): void
    {
        $container->singleton(WorkspaceRepository::class, fn (Container $c) => new PdoWorkspaceRepository($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(WorkspaceService::class, fn (Container $c) => $c->build(WorkspaceService::class));
        $container->singleton(WorkspaceDirectory::class, fn (Container $c) => $c->get(WorkspaceService::class));
        $container->singleton(WorkspaceProvisioning::class, fn (Container $c) => $c->get(WorkspaceService::class));
        $container->singleton(WorkspaceMembership::class, fn (Container $c) => $c->get(WorkspaceService::class));

        // Every page with the app header gets the workspace switcher data.
        $container->extend(View::class, function (object $view, Container $c): void {
            /** @var View $view */
            $view->composer('partials/app-header', function () use ($c): array {
                $user = $c->get(Authenticator::class)->user();

                if ($user === null) {
                    return [];
                }

                $workspaces = $c->get(WorkspaceDirectory::class);

                return [
                    'user'             => $user,
                    'workspaces'       => $workspaces->workspacesOf($user->id),
                    'currentWorkspace' => $workspaces->current($user->id),
                ];
            });
        });
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->get('/workspaces/new', [WorkspaceController::class, 'create']);
            $router->post('/workspaces', [WorkspaceController::class, 'store']);
            $router->post('/workspaces/{workspace:ulid}/switch', [WorkspaceController::class, 'switch']);
            $router->get('/workspaces/{workspace:ulid}/members', [WorkspaceController::class, 'members']);
            $router->post('/workspaces/{workspace:ulid}/members/{id}', [WorkspaceController::class, 'changeRole']);
            $router->post('/workspaces/{workspace:ulid}/members/{id}/remove', [WorkspaceController::class, 'removeMember']);
            $router->post('/workspaces/{workspace:ulid}/leave', [WorkspaceController::class, 'leave']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
        $provision = static function (UserRegistered|UserLoggedIn $event) use ($container): void {
            $container->get(WorkspaceProvisioning::class)->ensureWorkspaceFor($event->userId, $event->name);
        };

        $events->listen(UserRegistered::name(), $provision);
        $events->listen(UserLoggedIn::name(), $provision);
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
