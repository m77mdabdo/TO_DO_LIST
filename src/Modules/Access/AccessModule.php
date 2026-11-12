<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Core\Container;
use App\Http\Router;
use App\Modules\Access\Application\Gate;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Access\Infrastructure\PdoBoardMembers;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\Events\BoardCreated;
use App\Modules\Workspace\Contract\Events\MemberRemoved;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use PDO;

/**
 * Board roles, permissions and the gate every board action goes through.
 * Listens to: board.created (the creator becomes the board's manager),
 *   workspace.member_removed (someone who leaves a workspace leaves its boards too).
 * Offers: Contract\AccessGate, Contract\BoardMembers.
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
    }

    public function routes(Router $router): void
    {
    }

    public function listeners(EventBus $events, Container $container): void
    {
        $events->listen(BoardCreated::name(), static function (BoardCreated $event) use ($container): void {
            $container->get(BoardMembers::class)->add($event->boardId, $event->actorId, BoardRole::Manager);
            $container->get(Gate::class)->forget($event->actorId, $event->boardId);
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
