<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Http\AuditController;
use App\Modules\Audit\Domain\AuditLog;
use App\Modules\Audit\Infrastructure\PdoAuditLog;
use App\Shared\Events\DomainEvent;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use App\View\View;
use PDO;

/**
 * The audit log of sensitive changes in a workspace (invitations, roles,
 * removals), for its owners and admins.
 * Listens to invitation, workspace member and board member events.
 */
final class AuditModule implements ModuleProvider
{
    public function name(): string
    {
        return 'audit';
    }

    public function register(Container $container): void
    {
        $container->singleton(AuditLog::class, fn (Container $c) => new PdoAuditLog($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(AuditRecorder::class, fn (Container $c) => $c->build(AuditRecorder::class));

        $container->extend(View::class, function (object $view): void {
            /** @var View $view */
            $view->addToSlot('workspace.members', 'audit/link');
        });
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->get('/workspaces/{workspace:ulid}/audit', [AuditController::class, 'index']);
            $router->get('/workspaces/{workspace:ulid}/audit.csv', [AuditController::class, 'csv']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
        foreach (AuditRecorder::events() as $name) {
            $events->listen($name, static function (DomainEvent $event) use ($container): void {
                $container->get(AuditRecorder::class)->record($event);
            });
        }

        // Kept in every workspace the person was in, without their name or email.
        $events->listen(\App\Modules\Identity\Contract\Events\AccountDeleted::name(), static function (\App\Modules\Identity\Contract\Events\AccountDeleted $e) use ($container): void {
            foreach ($container->get(\App\Modules\Workspace\Contract\WorkspaceDirectory::class)->workspacesOf($e->userId) as $workspace) {
                $container->get(AuditLog::class)->add($workspace->id, null, 'account.deleted', 'Deleted user #' . $e->userId);
            }
        });
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
