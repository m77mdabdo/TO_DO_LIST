<?php

declare(strict_types=1);

namespace App\Modules\Card;

use App\Modules\Board\Contract\Events\BoardCopied;
use App\Shared\Automation\ActionContext;
use App\Shared\Automation\ActionType;
use App\Shared\Automation\AutomationActions;
use App\Modules\Card\Application\CardCopier;
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
        $container->extend(AutomationActions::class, function (object $actions, Container $c): void {
            /** @var AutomationActions $actions */
            $service = static fn (): CardService => $c->get(CardService::class);
            $card = static fn (ActionContext $ctx) => $c->get(CardQueries::class)->find((int) $ctx->cardId) ?? throw new \RuntimeException('The card is gone.');

            $actions->add(new ActionType('card.move', 'Move the card to list :value', 'list', static fn (ActionContext $ctx, string $v) => $service()->moveToEnd($ctx->actorId, $card($ctx), (int) $v)));
            $actions->add(new ActionType('card.complete', 'Mark the card complete', 'none', static fn (ActionContext $ctx) => $service()->setComplete($ctx->actorId, $card($ctx), true)));
            $actions->add(new ActionType('card.reopen', 'Mark the card not complete', 'none', static fn (ActionContext $ctx) => $service()->setComplete($ctx->actorId, $card($ctx), false)));
            $actions->add(new ActionType('card.assign', 'Add :value to the card', 'person', static function (ActionContext $ctx, string $v) use ($service, $card): void {
                $target = $card($ctx);
                if (!in_array($ctx->person($v), $service()->membersOf($target), true)) {
                    $service()->toggleMember($ctx->actorId, $target, $ctx->person($v));
                }
            }));
            $actions->add(new ActionType('card.unassign', 'Remove :value from the card', 'person', static function (ActionContext $ctx, string $v) use ($service, $card): void {
                $target = $card($ctx);
                if (in_array($ctx->person($v), $service()->membersOf($target), true)) {
                    $service()->toggleMember($ctx->actorId, $target, $ctx->person($v));
                }
            }));
            $actions->add(new ActionType('card.due_in', 'Set the due date :value days from now', 'days', static fn (ActionContext $ctx, string $v) => $service()->setDueDate(
                $ctx->actorId,
                $card($ctx),
                $c->get(Clock::class)->now()->modify('+' . max(0, (int) $v) . ' days')->format('Y-m-d'),
            )));
            $actions->add(new ActionType('card.clear_due', 'Remove the due date', 'none', static fn (ActionContext $ctx) => $service()->setDueDate($ctx->actorId, $card($ctx), null)));
            $actions->add(new ActionType('card.archive', 'Archive the card', 'none', static fn (ActionContext $ctx) => $service()->archive($ctx->actorId, $card($ctx))));
            $actions->add(new ActionType('list.sort_due', 'Sort the list by due date', 'none', static fn (ActionContext $ctx) => $service()->sortList($ctx->actorId, $ctx->boardId, (int) $ctx->listId, 'due'), 'list'));
            $actions->add(new ActionType('list.sort_title', 'Sort the list by title', 'none', static fn (ActionContext $ctx) => $service()->sortList($ctx->actorId, $ctx->boardId, (int) $ctx->listId, 'title'), 'list'));
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
        $events->listen(BoardCopied::name(), static function (BoardCopied $event) use ($container): void {
            $container->get(CardCopier::class)->onBoardCopied($event);
        });
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
