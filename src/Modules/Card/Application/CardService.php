<?php

declare(strict_types=1);

namespace App\Modules\Card\Application;

use App\Database\Transactions;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Card\Contract\CardWriter;
use App\Modules\Card\Contract\Events\CardChanged;
use App\Modules\Card\Contract\Events\CardCreated;
use App\Modules\Card\Contract\Events\CardDeleted;
use App\Modules\Card\Contract\Events\CardMoved;
use App\Modules\Card\Domain\CardRepository;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use App\Validation\Validator;
use InvalidArgumentException;

/**
 * Card use cases. Each one authorizes the actor on the card's board first,
 * refuses lists from other boards, and announces what changed.
 */
final class CardService implements CardWriter
{
    public const TITLE_MAX = 200;
    public const DESCRIPTION_MAX = 10000;

    public function __construct(
        private CardRepository $cards,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private Transactions $transactions,
        private EventBus $events,
        private Clock $clock,
        private Validator $validator,
    ) {
    }

    /**
     * The card and its board, only if the user may do $permission there.
     * Unknown ids and boards the user can't see both give a 404.
     *
     * @return array{0: CardSummary, 1: BoardSummary}
     */
    public function cardFor(int $userId, string $publicId, Permission $permission = Permission::ViewBoard): array
    {
        $card = $this->cards->findByPublicId($publicId) ?? throw AccessDenied::hidden();
        $board = $this->boards->find($card->boardId) ?? throw AccessDenied::hidden();
        $this->gate->authorize($userId, $board->id, $permission);

        return [$card, $board];
    }

    public function createCard(int $actorId, int $boardId, int $listId, string $title, array $extra = []): CardSummary
    {
        $this->gate->authorize($actorId, $boardId, Permission::EditCards);
        $this->assertOpenList($boardId, $listId);
        $this->assertValid(['title' => $title, 'due_date' => $extra['due_date'] ?? ''], [
            'title'    => ['Title', 'required|max:' . self::TITLE_MAX],
            'due_date' => ['Due date', 'date'],
        ]);

        return $this->transactions->run(function () use ($actorId, $boardId, $listId, $title, $extra): CardSummary {
            $card = $this->cards->create($boardId, $listId, trim($title), $this->cards->positionAtEnd($listId), $actorId, [
                'description'  => $extra['description'] ?? null,
                'due_at'       => self::dueAt($extra['due_date'] ?? null),
                'completed_at' => $extra['completed_at'] ?? null,
                'created_at'   => $extra['created_at'] ?? null,
            ]);
            $this->events->publish(new CardCreated($card->id, $boardId, $listId, $actorId, $card->title, $this->clock->now()));

            return $card;
        });
    }

    /** @param array<string, mixed> $input title, description, due_date */
    public function update(int $actorId, CardSummary $card, array $input): void
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::EditCards);
        $this->assertBoardOpen($card->boardId);
        $this->assertValid($input, [
            'title'       => ['Title', 'required|max:' . self::TITLE_MAX],
            'description' => ['Description', 'max:' . self::DESCRIPTION_MAX],
            'due_date'    => ['Due date', 'date'],
        ]);

        $fields = [
            'title'       => trim((string) $input['title']),
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'due_at'      => self::dueAt((string) ($input['due_date'] ?? '')),
        ];

        $changes = array_filter([
            'renamed'     => $fields['title'] !== $card->title ? ['from' => $card->title] : null,
            'described'   => $fields['description'] !== $card->description ? [] : null,
            'due_changed' => $fields['due_at'] !== $card->dueAt ? ['to' => $fields['due_at'] ? substr($fields['due_at'], 0, 10) : null] : null,
        ], static fn ($change) => $change !== null);

        if ($changes === []) {
            return;
        }

        $this->transactions->run(function () use ($actorId, $card, $fields, $changes): void {
            $this->cards->update($card->id, $fields);

            foreach ($changes as $change => $details) {
                $this->events->publish(new CardChanged($card->id, $card->boardId, $actorId, $change, $fields['title'], $details, $this->clock->now()));
            }
        });
    }

    public function setComplete(int $actorId, CardSummary $card, bool $complete): void
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::EditCards);
        $this->assertBoardOpen($card->boardId);

        if ($complete === $card->isComplete()) {
            return;
        }

        $this->transactions->run(function () use ($actorId, $card, $complete): void {
            $this->cards->update($card->id, ['completed_at' => $complete ? $this->clock->now()->format('Y-m-d H:i:s') : null]);
            $this->events->publish(new CardChanged($card->id, $card->boardId, $actorId, $complete ? 'completed' : 'reopened', $card->title, [], $this->clock->now()));
        });
    }

    /**
     * Moves the card into $toListId right after $afterCardId (top when null).
     * Only the moved card's row is written.
     */
    public function move(int $actorId, CardSummary $card, int $toListId, ?int $afterCardId): CardSummary
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::MoveCards);
        $this->assertOpenList($card->boardId, $toListId);

        return $this->transactions->run(function () use ($actorId, $card, $toListId, $afterCardId): CardSummary {
            try {
                $position = $this->cards->positionAfter($toListId, $afterCardId, $card->id);
            } catch (InvalidArgumentException) {
                throw new ValidationException(new ValidationResult(['card' => 'That move is not allowed.']));
            }

            $this->cards->update($card->id, ['list_id' => $toListId, 'position' => $position]);
            $this->events->publish(new CardMoved($card->id, $card->boardId, $actorId, $card->title, $card->listId, $toListId, $this->clock->now()));

            return $this->cards->find($card->id);
        });
    }

    public function archive(int $actorId, CardSummary $card, bool $archive = true): void
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::ArchiveItems);
        $this->assertBoardOpen($card->boardId);

        if ($archive === $card->archived) {
            return;
        }

        if (!$archive) {
            // Restoring into a list that was archived meanwhile would hide the card again.
            $this->assertOpenList($card->boardId, $card->listId);
        }

        $this->transactions->run(function () use ($actorId, $card, $archive): void {
            $this->cards->setArchived($card->id, $archive);
            $this->events->publish(new CardChanged($card->id, $card->boardId, $actorId, $archive ? 'archived' : 'restored', $card->title, [], $this->clock->now()));
        });
    }

    /** Permanent. Only archived cards, only for those allowed to delete. */
    public function delete(int $actorId, CardSummary $card): void
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::DeleteItems);

        if (!$card->archived) {
            throw new ValidationException(new ValidationResult(['card' => 'Archive the card before deleting it.']));
        }

        $this->transactions->run(function () use ($actorId, $card): void {
            $this->events->publish(new CardDeleted($card->id, $card->boardId, $actorId, $card->title, $this->clock->now()));
            $this->cards->delete($card->id);
        });
    }

    private function assertOpenList(int $boardId, int $listId): void
    {
        $this->assertBoardOpen($boardId);
        $list = $this->boards->listOnBoard($listId, $boardId);

        // A list id from another board is treated exactly like a missing one (no IDOR).
        if ($list === null || $list->archived) {
            throw new ValidationException(new ValidationResult(['list' => 'That list is not available.']));
        }
    }

    private function assertBoardOpen(int $boardId): void
    {
        if ($this->boards->find($boardId)?->archived ?? true) {
            throw AccessDenied::forbidden();
        }
    }

    private static function dueAt(?string $date): ?string
    {
        return $date !== null && $date !== '' ? $date . ' 00:00:00' : null;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, array{0: string, 1: string}> $rules
     */
    private function assertValid(array $input, array $rules): void
    {
        $result = $this->validator->validate($input, $rules);

        if ($result->fails()) {
            throw new ValidationException($result);
        }
    }
}
