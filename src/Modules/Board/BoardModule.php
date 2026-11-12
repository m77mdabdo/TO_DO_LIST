<?php

declare(strict_types=1);

namespace App\Modules\Board;

use App\Core\Container;
use App\Http\Router;
use App\Modules\Board\Application\BoardQueries;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardCatalog;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Domain\BoardRepository;
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
    }

    public function listeners(EventBus $events, Container $container): void
    {
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
