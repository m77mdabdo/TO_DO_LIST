<?php

declare(strict_types=1);

namespace App\Modules\Checklist\Application;

use App\Modules\Checklist\Contract\ChecklistQueries;
use App\Database\Transactions;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardWriter;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Checklist\Contract\Events\ChecklistChanged;
use App\Modules\Checklist\Contract\Checklist;
use App\Modules\Checklist\Contract\ChecklistItem;
use App\Modules\Checklist\Domain\ChecklistRepository;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use App\Validation\Validator;
use InvalidArgumentException;

/**
 * Checklists on a card and their items. Every change is authorized on the
 * card's board (EditCards), and refused on archived cards and boards.
 * Ids that don't exist and ids on boards the user can't see both give a 404.
 */
final class ChecklistService implements ChecklistQueries
{
    public const MAX_CHECKLISTS = 20;
    public const MAX_ITEMS = 200;

    public function __construct(
        private ChecklistRepository $checklists,
        private CardQueries $cards,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private Transactions $transactions,
        private EventBus $events,
        private Clock $clock,
        private Validator $validator,
        private CardWriter $writer,
    ) {
    }

    /** @return list<Checklist> */
    public function forCard(int $cardId): array
    {
        return $this->checklists->forCard($cardId);
    }

    /**
     * @param list<int> $cardIds
     * @return array<int, array{done: int, total: int}>
     */
    public function progressForCards(array $cardIds): array
    {
        return $this->checklists->progressForCards($cardIds);
    }

    public function openItemsAssignedTo(int $userId): array
    {
        return $this->checklists->openItemsAssignedTo($userId);
    }

    /**
     * @param list<int> $cardIds
     * @return list<ChecklistItem>
     */
    public function itemsDueBetween(array $cardIds, string $from, string $to): array
    {
        return $this->checklists->itemsDueBetween($cardIds, $from, $to);
    }

    public function addChecklist(int $actorId, CardSummary $card, string $title): int
    {
        $this->authorize($actorId, $card);
        $title = $this->validTitle($title);

        if ($this->checklists->countOnCard($card->id) >= self::MAX_CHECKLISTS) {
            throw self::invalid('checklist', 'A card can have up to 20 checklists.');
        }

        return $this->transactions->run(function () use ($actorId, $card, $title): int {
            $id = $this->checklists->create($card->id, $title, $actorId);
            $this->announce($card, $actorId, 'added', $title);

            return $id;
        });
    }

    public function renameChecklist(int $actorId, int $checklistId, string $title): CardSummary
    {
        [$checklist, $card] = $this->checklistFor($actorId, $checklistId);
        $title = $this->validTitle($title);

        if ($title !== $checklist->title) {
            $this->transactions->run(function () use ($actorId, $card, $checklist, $title): void {
                $this->checklists->rename($checklist->id, $title);
                $this->announce($card, $actorId, 'renamed', $title);
            });
        }

        return $card;
    }

    public function deleteChecklist(int $actorId, int $checklistId): CardSummary
    {
        [$checklist, $card] = $this->checklistFor($actorId, $checklistId);

        $this->transactions->run(function () use ($actorId, $card, $checklist): void {
            $this->checklists->delete($checklist->id);
            $this->announce($card, $actorId, 'removed', $checklist->title);
        });

        return $card;
    }

    /**
     * Adds one item, or one item per line when several lines are pasted
     * (blank lines are skipped). All are checked first; none is added if
     * one is too long or they would not fit.
     */
    public function addItem(int $actorId, int $checklistId, string $text): CardSummary
    {
        [$checklist, $card] = $this->checklistFor($actorId, $checklistId);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), static fn (string $line) => $line !== ''));
        if ($lines === []) {
            $this->validText('');
        }
        foreach ($lines as $line) {
            $this->validText($line);
        }

        if ($this->checklists->countItems($checklist->id) + count($lines) > self::MAX_ITEMS) {
            throw self::invalid('text', 'A checklist can have up to 200 items.');
        }

        $this->transactions->run(function () use ($lines, $checklist, $card, $actorId): void {
            foreach ($lines as $line) {
                $this->checklists->addItem($checklist->id, $card->id, $line, $actorId);
            }
        });

        return $card;
    }

    /**
     * Copies a checklist from another card of the same board onto this card:
     * same title and items, all unticked, keeping who they are assigned to
     * and their due dates.
     */
    public function copyChecklist(int $actorId, CardSummary $card, int $sourceId): CardSummary
    {
        $this->authorize($actorId, $card);
        $source = $this->checklists->find($sourceId);
        $sourceCard = $source !== null ? $this->cards->find($source->cardId) : null;
        if ($source === null || $sourceCard === null || $sourceCard->boardId !== $card->boardId || $sourceCard->archived) {
            throw self::invalid('copy_from', 'Choose a checklist from this board.');
        }
        if ($this->checklists->countOnCard($card->id) >= self::MAX_CHECKLISTS) {
            throw self::invalid('copy_from', 'A card can have up to 20 checklists.');
        }
        $items = [];
        foreach ($this->checklists->forCard($sourceCard->id) as $list) {
            if ($list->id === $source->id) {
                $items = $list->items;
            }
        }

        $this->transactions->run(function () use ($actorId, $card, $source, $items): void {
            $id = $this->checklists->create($card->id, $source->title, $actorId);
            foreach (array_slice($items, 0, self::MAX_ITEMS) as $item) {
                $itemId = $this->checklists->addItem($id, $card->id, $item->text, $actorId);
                $this->checklists->updateItem($itemId, ['assignee_id' => $item->assigneeId, 'due_at' => $item->dueAt]);
            }
            $this->announce($card, $actorId, 'added', $source->title);
        });

        return $card;
    }

    /** @return list<array{id: int, title: string, card: string, items: int}> checklists to copy from */
    public function copySources(CardSummary $card): array
    {
        return $this->checklists->onBoard($card->boardId, $card->id);
    }

    /**
     * Hides or shows the ticked items of a checklist, for this person only.
     * Anyone who can see the card may choose.
     */
    public function setHideDone(int $userId, int $checklistId, bool $hide): CardSummary
    {
        $card = $this->cardOfChecklist($userId, $checklistId);
        $this->checklists->setHidden($userId, $checklistId, $hide);

        return $card;
    }

    /**
     * @param list<int> $checklistIds
     * @return list<int> the ones whose ticked items the user hides
     */
    public function hiddenFor(int $userId, array $checklistIds): array
    {
        return $this->checklists->hiddenFor($userId, $checklistIds);
    }

    /**
     * Turns an item into a card in a list the actor chooses (on any board of
     * the workspace where they may add cards). The card gets the item's
     * text, due date and assignee (if that person can see the target board),
     * and a description that links back; the item keeps a link to the card.
     */
    public function convertItem(int $actorId, int $itemId, int $boardId, int $listId): CardSummary
    {
        [$item, $card] = $this->itemFor($actorId, $itemId);
        $from = $this->boards->find($card->boardId);
        $target = $this->boards->find($boardId);
        if ($from === null || $target === null || $target->workspaceId !== $from->workspaceId || $target->archived
            || !$this->gate->can($actorId, $target->id, Permission::EditCards)
            || $this->boards->listOnBoard($listId, $target->id) === null) {
            throw self::invalid('convert_to', 'Choose a list on a board of this workspace where you can add cards.');
        }
        $checklist = $this->checklists->find($item->checklistId);

        return $this->transactions->run(function () use ($actorId, $item, $card, $target, $listId, $checklist): CardSummary {
            $new = $this->writer->createCard($actorId, $target->id, $listId, mb_substr($item->text, 0, 200), [
                'description' => 'From the checklist “' . ($checklist?->title ?? '') . '” on [' . str_replace(['[', ']'], ['(', ')'], $card->title) . '](/cards/' . $card->publicId . ')',
                'due_date'    => $item->dueDate(),
            ]);
            if ($item->assigneeId !== null) {
                $this->writer->assign($actorId, $new, $item->assigneeId);
            }
            $this->checklists->updateItem($item->id, ['card_link_id' => $new->id]);
            $this->announce($card, $actorId, 'item_converted', $item->text);

            return $new;
        });
    }

    /**
     * Edits an item's text, who it is assigned to and its due date.
     *
     * @param array<string, mixed> $input text, assignee_id ('' for nobody), due_date (Y-m-d or '')
     */
    public function updateItem(int $actorId, int $itemId, array $input): CardSummary
    {
        [$item, $card] = $this->itemFor($actorId, $itemId);

        $result = $this->validator->validate($input, [
            'text'     => ['Item', 'required|max:500'],
            'due_date' => ['Due date', 'date'],
        ]);
        if ($result->fails()) {
            throw new ValidationException($result);
        }

        $assignee = (string) ($input['assignee_id'] ?? '');
        $assigneeId = ctype_digit($assignee) ? (int) $assignee : null;
        if ($assigneeId !== null && !$this->gate->can($assigneeId, $card->boardId, Permission::ViewBoard)) {
            throw self::invalid('assignee_id', "That person can't see this board.");
        }

        $due = (string) ($input['due_date'] ?? '');
        $text = trim((string) $input['text']);
        $this->checklists->updateItem($item->id, [
            'text'        => $text,
            'assignee_id' => $assigneeId,
            'due_at'      => $due !== '' ? $due . ' 00:00:00' : null,
        ]);

        if ($assigneeId !== null && $assigneeId !== $item->assigneeId) {
            $this->announce($card, $actorId, 'item_assigned', $text, $assigneeId);
        }

        return $card;
    }

    /** Ticks the item, or unticks it. Returns whether it is now done. */
    public function toggleItem(int $actorId, int $itemId): bool
    {
        [$item, $card] = $this->itemFor($actorId, $itemId);
        $done = !$item->isDone();

        $this->transactions->run(function () use ($actorId, $card, $item, $done): void {
            $this->checklists->updateItem($item->id, ['done_at' => $done ? $this->clock->now()->format('Y-m-d H:i:s') : null]);
            $this->announce($card, $actorId, $done ? 'item_completed' : 'item_reopened', $item->text);
        });

        return $done;
    }

    /** Puts the item into $toChecklistId (a checklist of the same card) right after $afterId. */
    public function moveItem(int $actorId, int $itemId, int $toChecklistId, ?int $afterId): CardSummary
    {
        [$item, $card] = $this->itemFor($actorId, $itemId);
        $target = $this->checklists->find($toChecklistId);

        if ($target === null || $target->cardId !== $card->id) {
            throw self::invalid('checklist', 'That move is not allowed.');
        }

        try {
            $this->transactions->run(fn () => $this->checklists->moveItem($item->id, $target->id, $afterId));
        } catch (InvalidArgumentException) {
            throw self::invalid('checklist', 'That move is not allowed.');
        }

        return $card;
    }

    public function deleteItem(int $actorId, int $itemId): CardSummary
    {
        [$item, $card] = $this->itemFor($actorId, $itemId);
        $this->checklists->deleteItem($item->id);

        return $card;
    }

    /** The card a checklist is on, for redirects. 404 when the user can't see it. */
    public function cardOfChecklist(int $userId, int $checklistId): CardSummary
    {
        $checklist = $this->checklists->find($checklistId) ?? throw AccessDenied::hidden();

        return $this->visibleCard($userId, $checklist->cardId);
    }

    /** The card an item is on, for redirects. 404 when the user can't see it. */
    public function cardOfItem(int $userId, int $itemId): CardSummary
    {
        $item = $this->checklists->findItem($itemId) ?? throw AccessDenied::hidden();

        return $this->visibleCard($userId, $item->cardId);
    }

    /** @return array{0: Checklist, 1: CardSummary} */
    private function checklistFor(int $actorId, int $checklistId): array
    {
        $checklist = $this->checklists->find($checklistId) ?? throw AccessDenied::hidden();
        $card = $this->cards->find($checklist->cardId) ?? throw AccessDenied::hidden();
        $this->authorize($actorId, $card);

        return [$checklist, $card];
    }

    /** @return array{0: ChecklistItem, 1: CardSummary} */
    private function itemFor(int $actorId, int $itemId): array
    {
        $item = $this->checklists->findItem($itemId) ?? throw AccessDenied::hidden();
        $card = $this->cards->find($item->cardId) ?? throw AccessDenied::hidden();
        $this->authorize($actorId, $card);

        return [$item, $card];
    }

    private function visibleCard(int $userId, int $cardId): CardSummary
    {
        $card = $this->cards->find($cardId) ?? throw AccessDenied::hidden();
        $this->gate->authorize($userId, $card->boardId, Permission::ViewBoard);

        return $card;
    }

    /** Edit rights on the board, which must be open, on a card that isn't archived. */
    private function authorize(int $actorId, CardSummary $card): void
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::EditCards);

        if ($card->archived || ($this->boards->find($card->boardId)?->archived ?? true)) {
            throw AccessDenied::forbidden();
        }
    }

    private function announce(CardSummary $card, int $actorId, string $change, string $subject, ?int $assigneeId = null): void
    {
        $this->events->publish(new ChecklistChanged($card->id, $card->boardId, $actorId, $change, $subject, $card->title, $this->clock->now(), $assigneeId));
    }

    private function validTitle(string $title): string
    {
        $result = $this->validator->validate(['checklist' => $title], ['checklist' => ['Checklist title', 'required|max:120']]);
        if ($result->fails()) {
            throw new ValidationException($result);
        }

        return trim($title);
    }

    private function validText(string $text): string
    {
        $result = $this->validator->validate(['text' => $text], ['text' => ['Item', 'required|max:500']]);
        if ($result->fails()) {
            throw new ValidationException($result);
        }

        return trim($text);
    }

    private static function invalid(string $field, string $message): ValidationException
    {
        return new ValidationException(new ValidationResult([$field => $message]));
    }
}
