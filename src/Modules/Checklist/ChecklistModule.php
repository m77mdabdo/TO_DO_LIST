<?php

declare(strict_types=1);

namespace App\Modules\Checklist;

use App\Shared\Export\ExportParts;
use App\Modules\Card\Contract\Events\CardCopied;
use App\Modules\Checklist\Application\ChecklistCopier;
use App\Modules\Checklist\Infrastructure\PdoChecklistSearch;
use App\Shared\Search\SearchSources;
use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Checklist\Application\ChecklistService;
use App\Modules\Checklist\Domain\ChecklistRepository;
use App\Modules\Checklist\Http\ChecklistController;
use App\Modules\Checklist\Infrastructure\PdoChecklists;
use App\Modules\Identity\Contract\UserDirectory;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use App\View\View;
use PDO;

/**
 * Checklists on cards: items with an assignee and a due date, reordering,
 * and progress on the card tile.
 * Publishes: checklist.changed.
 * Adds to the card page (slot "card.main") and the board's card tiles.
 */
final class ChecklistModule implements ModuleProvider
{
    public function name(): string
    {
        return 'checklist';
    }

    public function register(Container $container): void
    {
        $container->extend(ExportParts::class, function (object $parts, Container $c): void {
            /** @var ExportParts $parts */
            $parts->add('checklists', function (array $cardIds) use ($c): array {
                $out = [];
                foreach ($cardIds as $cardId) {
                    foreach ($c->get(ChecklistService::class)->forCard($cardId) as $checklist) {
                        $out[$cardId][] = ['title' => $checklist->title, 'items' => array_map(static fn ($i) => [
                            'text' => $i->text, 'done' => $i->isDone(), 'due' => $i->dueDate(),
                        ], $checklist->items)];
                    }
                }

                return $out;
            });
        });
        $container->extend(SearchSources::class, function (object $sources, Container $c): void {
            /** @var SearchSources $sources */
            $sources->add(new PdoChecklistSearch($c->get(PDO::class)));
        });
        $container->singleton(ChecklistRepository::class, fn (Container $c) => new PdoChecklists($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(ChecklistService::class, fn (Container $c) => $c->build(ChecklistService::class));

        $container->extend(View::class, function (object $view, Container $c): void {
            /** @var View $view */
            $view->addToSlot('card.main', 'checklists/panel');
            $view->composer('checklists/panel', function (array $data) use ($c): array {
                $card = $data['card'];
                $gate = $c->get(AccessGate::class);
                $audience = $gate->peopleOn($card->boardId);

                return [
                    'checklists' => $c->get(ChecklistService::class)->forCard($card->id),
                    'audience'   => $c->get(UserDirectory::class)->findMany(array_keys($audience)),
                    'canEditChecklists' => !$card->archived && !$data['board']->archived && $data['role']->allows(Permission::EditCards),
                ];
            });

            // Progress on every tile of a board page, from one grouped query.
            $view->composer('boards/show', function (array $data) use ($c): array {
                $cardIds = [];
                foreach ($data['cardsByList'] ?? [] as $cards) {
                    foreach ($cards as $card) {
                        $cardIds[] = $card->id;
                    }
                }

                $badges = $data['cardBadges'] ?? [];
                foreach ($c->get(ChecklistService::class)->progressForCards($cardIds) as $cardId => ['done' => $done, 'total' => $total]) {
                    $badges[$cardId][] = [
                        'icon'  => 'checklist',
                        'text'  => $done . '/' . $total,
                        'class' => $done === $total ? 'badge-complete' : '',
                        'title' => 'Checklist items done',
                    ];
                }

                return ['cardBadges' => $badges];
            });

            // Progress per row of the table view.
            $view->composer('boards/table', fn (array $data): array => [
                'progress' => $c->get(ChecklistService::class)->progressForCards(array_map(static fn ($card) => $card->id, $data['rows'] ?? [])),
            ]);

            // Dated checklist items in the calendar, on the cards shown there.
            $view->composer('boards/calendar', function (array $data) use ($c): array {
                $items = [];
                $grid = $data['grid'];
                foreach ($c->get(ChecklistService::class)->itemsDueBetween(array_keys($data['cardById'] ?? []), $grid->first(), $grid->last()) as $item) {
                    $items[(string) $item->dueDate()][] = $item;
                }

                return ['checklistItems' => $items];
            });
        });
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->post('/cards/{card:ulid}/checklists', [ChecklistController::class, 'store']);
            $router->post('/checklists/{id}', [ChecklistController::class, 'rename']);
            $router->post('/checklists/{id}/delete', [ChecklistController::class, 'destroy']);
            $router->post('/checklists/{id}/items', [ChecklistController::class, 'addItem']);
            $router->post('/checklist-items/{id}', [ChecklistController::class, 'updateItem']);
            $router->post('/checklist-items/{id}/toggle', [ChecklistController::class, 'toggleItem']);
            $router->post('/checklist-items/{id}/move', [ChecklistController::class, 'moveItem']);
            $router->post('/checklist-items/{id}/delete', [ChecklistController::class, 'destroyItem']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
        $events->listen(CardCopied::name(), static fn (CardCopied $event) => $container->get(ChecklistCopier::class)->onCardCopied($event));
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
