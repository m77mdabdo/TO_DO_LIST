<?php

declare(strict_types=1);

namespace App\Modules\Import;

use App\Core\Config;
use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Import\Application\CsvImport;
use App\Modules\Import\Application\ImportStore;
use App\Modules\Import\Application\TrelloImport;
use App\Modules\Import\Http\ImportController;
use App\Security\RateLimitRule;
use App\Security\RateLimits;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use App\View\View;
use PDO;

/**
 * Bringing cards in: a CSV file into a board, or a Trello JSON export as a
 * new board. Each import is read, previewed, then run in one transaction
 * (or cancelled). It writes only through other modules' contracts, so every
 * card, label, checklist and comment gets the same checks as the UI.
 */
final class ImportModule implements ModuleProvider
{
    public function name(): string
    {
        return 'import';
    }

    public function register(Container $container): void
    {
        $container->singleton(ImportStore::class, fn (Container $c) => new ImportStore(
            $c->get(PDO::class),
            $c->get(Clock::class),
            (string) $c->get(Config::class)->get('imports.path', dirname(__DIR__, 3) . '/storage/imports'),
        ));
        $container->singleton(CsvImport::class, fn (Container $c) => $c->build(CsvImport::class));
        $container->singleton(TrelloImport::class, fn (Container $c) => $c->build(TrelloImport::class));

        $container->extend(RateLimits::class, function (object $limits): void {
            /** @var RateLimits $limits */
            $limits->add(new RateLimitRule('import-upload', 'POST', '/boards/*/import', 20, 3600, 'user'));
            $limits->add(new RateLimitRule('import-trello', 'POST', '/import/trello', 20, 3600, 'user'));
        });

        $container->extend(View::class, function (object $view): void {
            /** @var View $view */
            $view->addToSlot('board.toolbar', 'imports/board-link');
            $view->addToSlot('boards.create', 'imports/boards-link');
        });
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->get('/boards/{board:ulid}/import', [ImportController::class, 'csvForm']);
            $router->post('/boards/{board:ulid}/import', [ImportController::class, 'csvUpload']);
            $router->get('/import/trello', [ImportController::class, 'trelloForm']);
            $router->post('/import/trello', [ImportController::class, 'trelloUpload']);
            $router->get('/imports/{import:ulid}', [ImportController::class, 'show']);
            $router->post('/imports/{import:ulid}', [ImportController::class, 'run']);
            $router->post('/imports/{import:ulid}/cancel', [ImportController::class, 'cancel']);
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
