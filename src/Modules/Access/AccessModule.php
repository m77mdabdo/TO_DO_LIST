<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Access\Application\BoardMemberService;
use App\Modules\Access\Http\BoardMembersController;
use App\Modules\Access\Application\Gate;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Access\Infrastructure\PdoBoardMembers;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\Events\BoardCopied;
use App\Modules\Board\Contract\Events\BoardCreated;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\Events\MemberRemoved;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use App\View\View;
use PDO;

/**
 * Board roles, permissions and the gate every board action goes through.
 * Listens to: board.created (the creator becomes the board's manager),
 *   workspace.member_removed (someone who leaves a workspace leaves its boards too).
 * Publishes: board.member_changed.
 * Offers: Contract\AccessGate, Contract\BoardMembers, and the board members page.
 */
final class AccessModule implements ModuleProvider
{
    public function name(): string
    {
        return 'access';
    }

    public function register(Container $container): void
    {
        $container->singleton(BoardMembers::class, fn (Container $c) => new PdoBoardMembers($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(Gate::class, fn (Container $c) => $c->build(Gate::class));
        $container->singleton(AccessGate::class, fn (Container $c) => $c->get(Gate::class));
        $container->singleton(BoardMemberService::class, fn (Container $c) => $c->build(BoardMemberService::class));

        $container->extend(View::class, function (object $view): void {
            /** @var View $view */
            $view->addToSlot('board.actions', 'access/board-action');
        });
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->get('/boards/{board:ulid}/members', [BoardMembersController::class, 'index']);
            $router->post('/boards/{board:ulid}/members', [BoardMembersController::class, 'store']);
            $router->post('/boards/{board:ulid}/members/{id}/remove', [BoardMembersController::class, 'remove']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
        $events->listen(BoardCreated::name(), static function (BoardCreated $event) use ($container): void {
            $container->get(BoardMembers::class)->add($event->boardId, $event->actorId, BoardRole::Manager);
            $container->get(Gate::class)->forget($event->actorId, $event->boardId);
        });

        // A copied board can take the source's people along, with the same roles,
        // as long as they still belong to the workspace.
        $events->listen(BoardCopied::name(), static function (BoardCopied $event) use ($container): void {
            if (!$event->withMembers) {
                return;
            }
            $members = $container->get(BoardMembers::class);
            $board = $container->get(BoardDirectory::class)->find($event->boardId);
            foreach ($members->membersOf($event->sourceBoardId) as $userId => $role) {
                if ($userId === $event->actorId || $board === null || $container->get(WorkspaceDirectory::class)->roleOf($userId, $board->workspaceId) === null) {
                    continue;
                }
                $members->add($event->boardId, $userId, $role);
                $container->get(Gate::class)->forget($userId, $event->boardId);
            }
        });

        // Board roles must never outlive the workspace membership they came with.
        $events->listen(MemberRemoved::name(), static function (MemberRemoved $event) use ($container): void {
            $members = $container->get(BoardMembers::class);
            foreach ($container->get(BoardDirectory::class)->allInWorkspace($event->workspaceId) as $board) {
                $members->remove($board->id, $event->userId);
                $container->get(Gate::class)->forget($event->userId, $board->id);
            }
        });
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
