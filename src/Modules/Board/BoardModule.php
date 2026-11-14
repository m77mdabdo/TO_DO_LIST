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
use App\Modules\Board\Domain\BoardRepository;
use App\Modules\Board\Http\BoardController;
use App\Modules\Board\Http\BoardViewsController;
use App\Modules\Board\Http\ListController;
use App\Modules\Board\Http\SearchController;
use App\Modules\Board\Infrastructure\PdoBoardRepository;
use App\Shared\Events\EventBus;
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
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->get('/', [BoardController::class, 'index']);
            $router->get('/search', [SearchController::class, 'index']);
            $router->post('/boards', [BoardController::class, 'store']);
            $router->get('/boards/{board:ulid}', [BoardController::class, 'show']);
            $router->post('/boards/{board:ulid}', [BoardController::class, 'update']);
            $router->post('/boards/{board:ulid}/archive', [BoardController::class, 'archive']);
            $router->post('/boards/{board:ulid}/restore', [BoardController::class, 'restore']);
            $router->get('/boards/{board:ulid}/archived', [BoardController::class, 'archived']);
            $router->get('/boards/{board:ulid}/export', [BoardController::class, 'export']);
            $router->get('/boards/{board:ulid}/table', [BoardViewsController::class, 'table']);
            $router->get('/boards/{board:ulid}/calendar', [BoardViewsController::class, 'calendar']);
            $router->get('/boards/{board:ulid}/dashboard', [BoardViewsController::class, 'dashboard']);

            $router->post('/boards/{board:ulid}/lists', [ListController::class, 'store']);
            $router->post('/boards/{board:ulid}/lists/{id}', [ListController::class, 'update']);
            $router->post('/boards/{board:ulid}/lists/{id}/move', [ListController::class, 'move']);
            $router->post('/boards/{board:ulid}/lists/{id}/archive', [ListController::class, 'archive']);
            $router->post('/boards/{board:ulid}/lists/{id}/restore', [ListController::class, 'restore']);
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
