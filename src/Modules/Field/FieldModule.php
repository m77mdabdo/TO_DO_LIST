<?php

declare(strict_types=1);

namespace App\Modules\Field;

use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\Events\BoardCopied;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\Events\CardCopied;
use App\Modules\Field\Application\FieldCopier;
use App\Modules\Field\Application\FieldService;
use App\Modules\Field\Domain\FieldRepository;
use App\Modules\Field\Http\FieldController;
use App\Modules\Field\Infrastructure\PdoFields;
use App\Modules\Field\Infrastructure\PdoFieldSearch;
use App\Modules\Identity\Contract\Authenticator;
use App\Shared\Automation\ActionContext;
use App\Shared\Automation\ActionType;
use App\Shared\Automation\AutomationActions;
use App\Shared\Events\EventBus;
use App\Shared\Export\ExportColumns;
use App\Shared\Export\ExportParts;
use App\Shared\Module\ModuleProvider;
use App\Shared\Search\SearchSources;
use App\Support\Clock;
use App\View\View;
use PDO;

/**
 * Custom fields: a board defines them (text, number, date, choice,
 * checkbox), each card holds its own values. Shown on the card page and,
 * when chosen, on the board's tiles; searchable; part of the CSV and JSON
 * exports and of card and board copies.
 * Publishes: field.value_changed, field.definitions_changed.
 */
final class FieldModule implements ModuleProvider
{
    public function name(): string
    {
        return 'field';
    }

    public function register(Container $container): void
    {
        $container->singleton(FieldRepository::class, fn (Container $c) => new PdoFields($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(FieldService::class, fn (Container $c) => $c->build(FieldService::class));
        $container->singleton(\App\Modules\Field\Contract\FieldValues::class, fn (Container $c) => $c->get(FieldService::class));

        $container->extend(SearchSources::class, function (object $sources, Container $c): void {
            /** @var SearchSources $sources */
            $sources->add(new PdoFieldSearch($c->get(PDO::class)));
        });

        $container->extend(ExportParts::class, function (object $parts, Container $c): void {
            /** @var ExportParts $parts */
            $parts->add('fields', function (array $cardIds) use ($c): array {
                $cards = $c->get(CardQueries::class)->findMany($cardIds);
                $out = [];
                $byBoard = [];
                foreach ($cards as $card) {
                    $byBoard[$card->boardId][] = $card->id;
                }
                foreach ($byBoard as $boardId => $ids) {
                    foreach ($c->get(FieldService::class)->shownForCards($boardId, $ids) as $cardId => $shown) {
                        foreach ($shown as ['field' => $field, 'text' => $text]) {
                            $out[$cardId][$field->name] = $field->type === 'checkbox' ? true : $text;
                        }
                    }
                }

                return $out;
            });
        });

        $container->extend(ExportColumns::class, function (object $columns, Container $c): void {
            /** @var ExportColumns $columns */
            $columns->add(function (int $boardId, array $cardIds) use ($c): array {
                $service = $c->get(FieldService::class);
                $fields = $service->forBoard($boardId);
                $values = $service->valuesForCards($cardIds);
                $cells = [];
                foreach ($cardIds as $cardId) {
                    $cells[$cardId] = array_map(static fn ($f) => $f->display($values[$cardId][$f->id] ?? null) === '✓' ? 'yes' : $f->display($values[$cardId][$f->id] ?? null), $fields);
                }

                return ['headers' => array_map(static fn ($f) => $f->name, $fields), 'cells' => $cells];
            });
        });

        $container->extend(AutomationActions::class, function (object $actions, Container $c): void {
            /** @var AutomationActions $actions */
            $actions->add(new ActionType('field.set', 'Set a custom field (write "Field: value")', 'text', static function (ActionContext $ctx, string $text) use ($c): void {
                $card = $c->get(CardQueries::class)->find((int) $ctx->cardId) ?? throw new \RuntimeException('The card is gone.');
                $board = $c->get(BoardDirectory::class)->find($card->boardId) ?? throw new \RuntimeException('The board is gone.');
                [$name, $value] = array_map('trim', explode(':', $text, 2) + [1 => '']);
                $service = $c->get(FieldService::class);
                foreach ($service->forBoard($board->id) as $field) {
                    if (mb_strtolower($field->name) !== mb_strtolower($name)) {
                        continue;
                    }
                    if ($field->type === 'select') {
                        foreach ($field->options as $option) {
                            if (mb_strtolower($option['name']) === mb_strtolower($value)) {
                                $value = $option['id'];
                            }
                        }
                    }
                    $service->fill($ctx->actorId, $board, $card, [$field->id => $value]);

                    return;
                }
                throw new \RuntimeException('This board has no custom field called "' . $name . '".');
            }));
        });

        $container->extend(View::class, function (object $view, Container $c): void {
            /** @var View $view */
            $view->addToSlot('card.side', 'fields/card');
            $userId = static fn (): int => $c->get(Authenticator::class)->user()?->id ?? 0;

            $view->composer('fields/card', function (array $data) use ($c, $userId): array {
                $service = $c->get(FieldService::class);

                return [
                    'fields'    => $service->forBoard($data['board']->id),
                    'values'    => $service->valuesForCards([$data['card']->id])[$data['card']->id] ?? [],
                    'canFill'   => $service->canFill($userId(), $data['board'], $data['card']),
                    'canManage' => $service->canManage($userId(), $data['board']),
                ];
            });

            // The values chosen to show on the tiles, from one query for the whole board.
            $view->composer('boards/show', function (array $data) use ($c): array {
                $cardIds = [];
                foreach ($data['cardsByList'] ?? [] as $cards) {
                    foreach ($cards as $card) {
                        $cardIds[] = $card->id;
                    }
                }

                return ['cardFields' => $c->get(FieldService::class)->shownForCards($data['board']->id, $cardIds, true)];
            });

            $view->composer('boards/table', function (array $data) use ($c): array {
                $service = $c->get(FieldService::class);

                return [
                    'fieldColumns' => $service->forBoard($data['board']->id),
                    'fieldValues'  => $service->valuesForCards(array_map(static fn ($card) => $card->id, $data['rows'] ?? [])),
                ];
            });
        });
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->get('/boards/{board:ulid}/fields', [FieldController::class, 'index']);
            $router->post('/boards/{board:ulid}/fields', [FieldController::class, 'store']);
            $router->post('/boards/{board:ulid}/fields/{id}', [FieldController::class, 'update']);
            $router->post('/boards/{board:ulid}/fields/{id}/move', [FieldController::class, 'move']);
            $router->post('/boards/{board:ulid}/fields/{id}/delete', [FieldController::class, 'destroy']);
            $router->post('/cards/{card:ulid}/fields', [FieldController::class, 'fill']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
        $events->listen(BoardCopied::name(), static fn (BoardCopied $event) => $container->get(FieldCopier::class)->onBoardCopied($event));
        $events->listen(CardCopied::name(), static fn (CardCopied $event) => $container->get(FieldCopier::class)->onCardCopied($event));
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
