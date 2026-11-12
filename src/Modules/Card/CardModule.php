<?php

declare(strict_types=1);

namespace App\Modules\Card;

use App\Core\Container;
use App\Http\Router;
use App\Modules\Card\Application\CardQueryService;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardWriter;
use App\Modules\Card\Domain\CardRepository;
use App\Modules\Card\Infrastructure\PdoCardRepository;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use PDO;

/**
 * Cards: create, edit, complete, move between lists, archive, delete.
 * Publishes: card.created, card.changed, card.moved, card.deleted.
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
        $container->singleton(CardRepository::class, fn (Container $c) => new PdoCardRepository($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(CardQueries::class, fn (Container $c) => $c->build(CardQueryService::class));
        $container->singleton(CardService::class, fn (Container $c) => $c->build(CardService::class));
        $container->singleton(CardWriter::class, fn (Container $c) => $c->get(CardService::class));
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
