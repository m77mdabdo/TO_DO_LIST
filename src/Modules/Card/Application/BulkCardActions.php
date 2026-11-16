<?php

declare(strict_types=1);

namespace App\Modules\Card\Application;

use App\Database\Transactions;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Card\Domain\CardRepository;
use App\Modules\Label\Contract\LabelCatalog;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;

/**
 * One action on many cards of one board. Every card must be an open card
 * of that board, the actor needs the action's permission there, and all of
 * it is one transaction: either every card changes or none does. Each card
 * still goes through the usual use case, so activity, notifications and
 * automations see each change.
 */
final class BulkCardActions
{
    public const MAX = 200;

    /** action => permission it needs on the board */
    public const ACTIONS = [
        'move'      => Permission::MoveCards,
        'complete'  => Permission::EditCards,
        'reopen'    => Permission::EditCards,
        'assign'    => Permission::EditCards,
        'unassign'  => Permission::EditCards,
        'label'     => Permission::EditCards,
        'unlabel'   => Permission::EditCards,
        'due'       => Permission::EditCards,
        'clear_due' => Permission::EditCards,
        'archive'   => Permission::ArchiveItems,
        'delete'    => Permission::DeleteItems,
    ];

    public function __construct(
        private CardService $service,
        private CardRepository $cards,
        private LabelCatalog $labels,
        private AccessGate $gate,
        private Transactions $transactions,
    ) {
    }

    /**
     * @param list<string> $publicIds the chosen cards
     * @param string $value the action's value: a list id, user id, label id or date
     * @param int $confirm for delete: the number of cards the person typed
     * @return int how many cards were acted on
     */
    public function apply(int $actorId, BoardSummary $board, array $publicIds, string $action, string $value, int $confirm = 0): int
    {
        $permission = self::ACTIONS[$action] ?? throw self::invalid('action', 'Choose what to do with the cards.');
        if ($board->archived) {
            throw AccessDenied::forbidden();
        }
        $this->gate->authorize($actorId, $board->id, $permission);
        if ($action === 'delete') {
            $this->gate->authorize($actorId, $board->id, Permission::ArchiveItems);
        }

        $cards = $this->resolve($board, $publicIds);
        if ($action === 'delete' && $confirm !== count($cards)) {
            throw self::invalid('confirm', 'Type the number of cards to delete them.');
        }

        return $this->transactions->run(function () use ($actorId, $cards, $action, $value): int {
            foreach ($cards as $card) {
                $this->one($actorId, $card, $action, $value);
            }

            return count($cards);
        });
    }

    private function one(int $actorId, CardSummary $card, string $action, string $value): void
    {
        $id = ctype_digit($value) ? (int) $value : 0;

        match ($action) {
            'move'      => $this->service->moveToEnd($actorId, $card, $id),
            'complete'  => $this->service->setComplete($actorId, $card, true),
            'reopen'    => $this->service->setComplete($actorId, $card, false),
            'assign'    => in_array($id, $this->service->membersOf($card), true) ?: $this->service->toggleMember($actorId, $card, $id),
            'unassign'  => !in_array($id, $this->service->membersOf($card), true) ?: $this->service->toggleMember($actorId, $card, $id),
            'label'     => $this->labels->attach($actorId, $card->id, $id),
            'unlabel'   => $this->labels->detach($actorId, $card->id, $id),
            'due'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $this->service->setDueDate($actorId, $card, $value) : throw self::invalid('value', 'Choose a date.'),
            'clear_due' => $this->service->setDueDate($actorId, $card, null),
            'archive'   => $this->service->archive($actorId, $card),
            'delete'    => $this->delete($actorId, $card),
        };
    }

    private function delete(int $actorId, CardSummary $card): void
    {
        $this->service->archive($actorId, $card);
        $this->service->delete($actorId, $this->cards->find($card->id) ?? $card);
    }

    /**
     * The chosen cards, each once, in board order. A card from another board,
     * an archived one or an unknown id rejects the whole request.
     *
     * @param list<string> $publicIds
     * @return list<CardSummary>
     */
    private function resolve(BoardSummary $board, array $publicIds): array
    {
        $publicIds = array_values(array_unique(array_filter($publicIds, static fn ($id) => is_string($id) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id) === 1)));
        if ($publicIds === []) {
            throw self::invalid('cards', 'Choose at least one card.');
        }
        if (count($publicIds) > self::MAX) {
            throw self::invalid('cards', 'Choose at most 200 cards at a time.');
        }

        $chosen = array_flip($publicIds);
        $cards = [];
        foreach ($this->cards->openCardsByList($board->id) as $listCards) {
            foreach ($listCards as $card) {
                if (isset($chosen[$card->publicId])) {
                    $cards[] = $card;
                }
            }
        }
        if (count($cards) !== count($publicIds)) {
            throw self::invalid('cards', 'Some of the chosen cards are no longer open on this board. Reload and try again.');
        }

        return $cards;
    }

    private static function invalid(string $field, string $message): ValidationException
    {
        return new ValidationException(new ValidationResult([$field => $message]));
    }
}
