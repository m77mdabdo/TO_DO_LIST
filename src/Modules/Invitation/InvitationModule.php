<?php

declare(strict_types=1);

namespace App\Modules\Invitation;

use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Board\Contract\BoardCatalog;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Invitation\Application\InvitationService;
use App\Modules\Invitation\Domain\InvitationRepository;
use App\Modules\Invitation\Http\InvitationController;
use App\Modules\Invitation\Infrastructure\PdoInvitations;
use App\Session\Session;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use App\View\View;
use PDO;

/**
 * Inviting people into a workspace (and some of its boards) by email or by
 * a link that works once.
 * Publishes: invitation.sent, invitation.accepted, invitation.revoked.
 * Adds the invite form and open invitations to the members page (slot "workspace.members").
 */
final class InvitationModule implements ModuleProvider
{
    public function name(): string
    {
        return 'invitation';
    }

    public function register(Container $container): void
    {
        $container->singleton(InvitationRepository::class, fn (Container $c) => new PdoInvitations($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(InvitationService::class, fn (Container $c) => $c->build(InvitationService::class));

        $container->extend(View::class, function (object $view, Container $c): void {
            /** @var View $view */
            $view->addToSlot('workspace.members', 'invitations/panel');

            $view->composer('invitations/panel', function (array $data) use ($c): array {
                $workspace = $data['workspace'];
                $open = $c->get(InvitationService::class)->openInvitations($data['viewerId'], $workspace->id);

                return [
                    'canInvite'   => $workspace->role->managesAllBoards(),
                    'invitations' => $open,
                    'inviters'    => $c->get(UserDirectory::class)->findMany(array_values(array_unique(array_map(static fn ($i) => $i->invitedBy, $open)))),
                    'boards'      => $c->get(BoardCatalog::class)->boardsInWorkspace($data['viewerId'], $workspace->id),
                    'freshLink'   => $c->get(Session::class)->pullFlash(InvitationController::LINK_FLASH),
                    'now'         => $c->get(Clock::class)->now(),
                ];
            });
        });
    }

    public function routes(Router $router): void
    {
        // The link itself works signed out too: it then asks the visitor to sign in or sign up.
        $router->get('/invitations/{token:token}', [InvitationController::class, 'show']);

        $router->group([Authenticate::class], function (Router $router): void {
            $router->post('/invitations/{token:token}/accept', [InvitationController::class, 'accept']);
            $router->post('/workspaces/{workspace:ulid}/invitations', [InvitationController::class, 'store']);
            $router->post('/invitations/{invitation:ulid}/resend', [InvitationController::class, 'resend']);
            $router->post('/invitations/{invitation:ulid}/revoke', [InvitationController::class, 'revoke']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
