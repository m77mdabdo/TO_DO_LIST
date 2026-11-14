<?php

declare(strict_types=1);

namespace App\Modules\Card;

use App\Modules\Card\Infrastructure\PdoCardSearch;
use App\Shared\Search\SearchSources;
use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Card\Application\CardQueryService;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardWriter;
use App\Modules\Card\Domain\CardRepository;
use App\Modules\Card\Http\CardController;
use App\Modules\Card\Infrastructure\PdoCardRepository;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use PDO;

/**
 * Cards: create, edit, complete, move between lists, archive, delete.
 * Publishes: card.created, card.changed, card.moved, card.member_changed, card.deleted.
 * Offers: Contract\CardQueries, Contract\CardWriter.
 */
final class CardModule implements ModuleProvider
{
    public function name(): string
    {
        return 'card';
    }

    public function register(Container $container): void
    {
        $container->extend(SearchSources::class, function (object $sources, Container $c): void {
            /** @var SearchSources $sources */
            $sources->add(new PdoCardSearch($c->get(PDO::class)));
        });
        $container->singleton(CardRepository::class, fn (Container $c) => new PdoCardRepository($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(CardQueries::class, fn (Container $c) => $c->build(CardQueryService::class));
        $container->singleton(CardService::class, fn (Container $c) => $c->build(CardService::class));
        $container->singleton(CardWriter::class, fn (Container $c) => $c->get(CardService::class));
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->post('/boards/{board:ulid}/cards', [CardController::class, 'store']);
            $router->get('/cards/{card:ulid}', [CardController::class, 'show']);
            $router->post('/cards/{card:ulid}', [CardController::class, 'update']);
            $router->post('/cards/{card:ulid}/members', [CardController::class, 'members']);
            $router->post('/cards/{card:ulid}/complete', [CardController::class, 'complete']);
            $router->post('/cards/{card:ulid}/move', [CardController::class, 'move']);
            $router->post('/cards/{card:ulid}/archive', [CardController::class, 'archive']);
            $router->post('/cards/{card:ulid}/restore', [CardController::class, 'restore']);
            $router->post('/cards/{card:ulid}/delete', [CardController::class, 'destroy']);
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
