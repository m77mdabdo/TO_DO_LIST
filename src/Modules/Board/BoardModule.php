<?php

declare(strict_types=1);

namespace App\Modules\Board;

use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Board\Application\BoardQueries;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardCatalog;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Domain\BoardPreferences;
use App\Modules\Board\Domain\BoardRepository;
use App\Modules\Board\Domain\BoardVersions;
use App\Modules\Board\Domain\SavedSearches;
use App\Modules\Board\Http\BoardController;
use App\Modules\Board\Http\BoardViewsController;
use App\Modules\Board\Http\WorkspaceViewsController;
use App\Modules\Board\Http\ListController;
use App\Modules\Board\Http\SearchController;
use App\Modules\Board\Infrastructure\PdoBoardPreferences;
use App\Modules\Board\Infrastructure\PdoBoardRepository;
use App\Modules\Board\Infrastructure\PdoBoardVersions;
use App\Modules\Board\Infrastructure\PdoSavedSearches;
use App\Shared\Events\DomainEvent;
use App\Shared\Events\EventBus;
use App\Shared\Events\SyncEventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use PDO;

/**
 * Boards and their lists: create, rename, reorder, archive.
 * Publishes: board.created, board.changed, list.created, list.changed.
 * Offers: Contract\BoardDirectory (reads), Contract\BoardCatalog (writes).
 */
final class BoardModule implements ModuleProvider
{
    public function name(): string
    {
        return 'board';
    }

    public function register(Container $container): void
    {
        $container->singleton(BoardRepository::class, fn (Container $c) => new PdoBoardRepository($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(BoardQueries::class, fn (Container $c) => $c->build(BoardQueries::class));
        $container->singleton(BoardDirectory::class, fn (Container $c) => $c->get(BoardQueries::class));
        $container->singleton(BoardService::class, fn (Container $c) => $c->build(BoardService::class));
        $container->singleton(BoardCatalog::class, fn (Container $c) => $c->get(BoardService::class));
        $container->singleton(SavedSearches::class, fn (Container $c) => new PdoSavedSearches($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(BoardPreferences::class, fn (Container $c) => new PdoBoardPreferences($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(\App\Modules\Board\Application\BoardGroups::class, fn (Container $c) => $c->build(\App\Modules\Board\Application\BoardGroups::class));
        $container->singleton(BoardVersions::class, fn (Container $c) => new PdoBoardVersions($c->get(PDO::class), $c->get(Clock::class)));
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->get('/', [BoardController::class, 'index']);
            $router->get('/search', [SearchController::class, 'index']);
            $router->post('/search/saved', [SearchController::class, 'save']);
            $router->post('/search/saved/{saved:ulid}', [SearchController::class, 'update']);
            $router->post('/search/saved/{saved:ulid}/delete', [SearchController::class, 'delete']);
            $router->post('/boards/{board:ulid}/display', [BoardController::class, 'display']);
            $router->get('/boards/{board:ulid}/changes', [BoardController::class, 'changes']);
            $router->post('/boards', [BoardController::class, 'store']);
            $router->get('/boards/{board:ulid}', [BoardController::class, 'show']);
            $router->post('/boards/{board:ulid}', [BoardController::class, 'update']);
            $router->post('/boards/{board:ulid}/archive', [BoardController::class, 'archive']);
            $router->post('/boards/{board:ulid}/restore', [BoardController::class, 'restore']);
            $router->get('/boards/{board:ulid}/archived', [BoardController::class, 'archived']);
            $router->get('/boards/{board:ulid}/export', [BoardController::class, 'export']);
            $router->get('/boards/{board:ulid}/export.json', [BoardController::class, 'exportJson']);
            $router->get('/workspaces/{workspace:ulid}/export.json', [BoardController::class, 'exportWorkspace']);
            $router->post('/boards/{board:ulid}/copy', [BoardController::class, 'copy']);
            $router->post('/boards/{board:ulid}/template', [BoardController::class, 'template']);
            $router->get('/boards/{board:ulid}/table', [BoardViewsController::class, 'table']);
            $router->get('/boards/{board:ulid}/calendar', [BoardViewsController::class, 'calendar']);
            $router->get('/boards/{board:ulid}/timeline', [BoardViewsController::class, 'timeline']);
            $router->get('/my-tasks', [WorkspaceViewsController::class, 'myTasks']);
            $router->get('/workspaces/{workspace:ulid}/cards', [WorkspaceViewsController::class, 'cards']);
            $router->get('/workspaces/{workspace:ulid}/report', [WorkspaceViewsController::class, 'report']);
            $router->get('/workspaces/{workspace:ulid}/report.csv', [WorkspaceViewsController::class, 'reportCsv']);
            $router->get('/boards/{board:ulid}/dashboard', [BoardViewsController::class, 'dashboard']);
            $router->post('/boards/{board:ulid}/table/settings', [BoardViewsController::class, 'saveTable']);
            $router->post('/boards/{board:ulid}/dashboard/widgets', [BoardViewsController::class, 'changeDashboard']);
            $router->get('/workspaces/{workspace:ulid}/calendar', [WorkspaceViewsController::class, 'calendar']);

            $router->post('/boards/{board:ulid}/lists', [ListController::class, 'store']);
            $router->post('/boards/{board:ulid}/lists/{id}', [ListController::class, 'update']);
            $router->post('/boards/{board:ulid}/lists/{id}/move', [ListController::class, 'move']);
            $router->post('/boards/{board:ulid}/lists/{id}/collapse', [ListController::class, 'collapse']);
            $router->post('/board-groups', [BoardController::class, 'groupStore']);
            $router->post('/board-groups/{id}', [BoardController::class, 'groupUpdate']);
            $router->post('/board-groups/{id}/move', [BoardController::class, 'groupMove']);
            $router->post('/board-groups/{id}/delete', [BoardController::class, 'groupDelete']);
            $router->post('/boards/{board:ulid}/group', [BoardController::class, 'place']);
            $router->post('/boards/{board:ulid}/lists/{id}/archive', [ListController::class, 'archive']);
            $router->post('/boards/{board:ulid}/lists/{id}/restore', [ListController::class, 'restore']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
        // Any event about a board (it carries boardId) marks that board as changed for open pages.
        $events->listen(SyncEventBus::ANY, static function (DomainEvent $event) use ($container): void {
            $boardId = property_exists($event, 'boardId') ? $event->boardId : null;
            if (is_int($boardId) && $boardId > 0) {
                $actorId = property_exists($event, 'actorId') && is_int($event->actorId) ? $event->actorId : null;
                $container->get(BoardVersions::class)->bump($boardId, $actorId);
            }
        });
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
