<?php

declare(strict_types=1);

namespace App\Modules\Card\Application;

use App\Database\Transactions;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Attachment\Contract\AttachmentFiles;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Card\Contract\CardWriter;
use App\Modules\Card\Contract\Events\CardChanged;
use App\Modules\Card\Contract\Events\CardCopied;
use App\Modules\Card\Contract\Events\CardCreated;
use App\Modules\Card\Contract\Events\CardDeleted;
use App\Modules\Card\Contract\Events\CardMemberChanged;
use App\Modules\Card\Contract\Events\CardMoved;
use App\Modules\Card\Domain\CardRepository;
use App\Shared\Events\EventBus;
use App\Shared\Ordering\FractionalIndex;
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
    /** What a card copy can keep. */
    public const COPY_PARTS = ['description', 'dates', 'labels', 'checklists', 'fields', 'members'];

    public function __construct(
        private CardRepository $cards,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private Transactions $transactions,
        private EventBus $events,
        private Clock $clock,
        private Validator $validator,
        private AttachmentFiles $files,
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

    /** @param array<string, mixed> $input title, description, start_date, due_date */
    public function update(int $actorId, CardSummary $card, array $input): void
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::EditCards);
        $this->assertBoardOpen($card->boardId);
        $this->assertValid($input, [
            'title'       => ['Title', 'required|max:' . self::TITLE_MAX],
            'description' => ['Description', 'max:' . self::DESCRIPTION_MAX],
            'start_date'  => ['Start date', 'date'],
            'due_date'    => ['Due date', 'date'],
        ]);

        $fields = [
            'title'       => trim((string) $input['title']),
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'start_at'    => array_key_exists('start_date', $input) ? self::dueAt((string) $input['start_date']) : $card->startAt,
            'due_at'      => self::dueAt((string) ($input['due_date'] ?? '')),
        ];

        if ($fields['start_at'] !== null && $fields['due_at'] !== null && $fields['start_at'] > $fields['due_at']) {
            throw new ValidationException(new ValidationResult(['start_date' => 'The start date must be on or before the due date.']));
        }

        $changes = array_filter([
            'renamed'     => $fields['title'] !== $card->title ? ['from' => $card->title] : null,
            'described'   => $fields['description'] !== $card->description ? [] : null,
            'start_changed' => $fields['start_at'] !== $card->startAt ? ['to' => $fields['start_at'] ? substr($fields['start_at'], 0, 10) : null] : null,
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

    /** Marks the card as a template (or a plain card again). */
    public function setTemplate(int $actorId, CardSummary $card, bool $template): void
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::EditCards);
        $this->assertBoardOpen($card->boardId);

        if ($template === $card->isTemplate) {
            return;
        }

        $this->transactions->run(function () use ($actorId, $card, $template): void {
            $this->cards->update($card->id, ['is_template' => $template ? 1 : 0]);
            $this->events->publish(new CardChanged($card->id, $card->boardId, $actorId, $template ? 'made_template' : 'unmade_template', $card->title, [], $this->clock->now()));
        });
    }

    /**
     * Changes how the card looks: its cover (none, a color, or one of the
     * card's own images, shown at the top or filling the tile), its sticker,
     * and its kind (an ordinary card, a separator, or a link to another
     * board in the same workspace that the actor can open).
     *
     * @param array<string, string> $input cover ("", "color:<color>", "image:<attachment public id>"), cover_size, sticker, kind, link_board (board public id)
     */
    public function setLooks(int $actorId, CardSummary $card, array $input): CardSummary
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::EditCards);
        $this->assertBoardOpen($card->boardId);
        if ($card->archived) {
            throw AccessDenied::forbidden();
        }

        $cover = (string) ($input['cover'] ?? '');
        $fields = ['cover_color' => null, 'cover_attachment_id' => null];
        if (str_starts_with($cover, 'color:') && in_array(substr($cover, 6), CardSummary::COVER_COLORS, true)) {
            $fields['cover_color'] = substr($cover, 6);
        } elseif (str_starts_with($cover, 'image:')) {
            foreach ($this->files->forCard($card->id) as $attachment) {
                if ($attachment->publicId === substr($cover, 6) && $attachment->isImage()) {
                    $fields['cover_attachment_id'] = $attachment->id;
                }
            }
            if ($fields['cover_attachment_id'] === null) {
                throw new ValidationException(new ValidationResult(['cover' => 'Choose one of this card’s images for the cover.']));
            }
        } elseif ($cover !== '') {
            throw new ValidationException(new ValidationResult(['cover' => 'Choose a cover from the list.']));
        }
        $fields['cover_size'] = in_array($input['cover_size'] ?? '', CardSummary::COVER_SIZES, true) ? (string) $input['cover_size'] : 'top';

        $sticker = (string) ($input['sticker'] ?? '');
        if ($sticker !== '' && !isset(CardSummary::STICKERS[$sticker])) {
            throw new ValidationException(new ValidationResult(['sticker' => 'Choose a sticker from the list.']));
        }
        $fields['sticker'] = $sticker !== '' ? $sticker : null;

        $kind = (string) ($input['kind'] ?? '') ?: $card->kind;
        if (!in_array($kind, CardSummary::KINDS, true)) {
            throw new ValidationException(new ValidationResult(['kind' => 'Choose what kind of card this is.']));
        }
        $fields['kind'] = $kind;
        $fields['link_board_id'] = null;
        if ($kind === 'board') {
            $from = $this->boards->find($card->boardId);
            $target = $this->boards->findByPublicId((string) ($input['link_board'] ?? ''));
            if ($from === null || $target === null || $target->workspaceId !== $from->workspaceId || $target->id === $from->id
                || !$this->gate->can($actorId, $target->id, Permission::ViewBoard)) {
                throw new ValidationException(new ValidationResult(['link_board' => 'Choose a board of this workspace that you can open.']));
            }
            $fields['link_board_id'] = $target->id;
        }

        $this->transactions->run(function () use ($actorId, $card, $fields): void {
            $this->cards->update($card->id, $fields);
            if ($fields['cover_color'] !== $card->coverColor || $fields['cover_attachment_id'] !== $card->coverAttachmentId || $fields['cover_size'] !== $card->coverSize) {
                $this->events->publish(new CardChanged($card->id, $card->boardId, $actorId, 'cover_changed', $card->title, [], $this->clock->now()));
            }
            if ($fields['sticker'] !== $card->sticker) {
                $this->events->publish(new CardChanged($card->id, $card->boardId, $actorId, 'sticker_changed', $card->title, [], $this->clock->now()));
            }
            if ($fields['kind'] !== $card->kind || $fields['link_board_id'] !== $card->linkBoardId) {
                $this->events->publish(new CardChanged($card->id, $card->boardId, $actorId, 'kind_changed', $card->title, ['kind' => $fields['kind']], $this->clock->now()));
            }
        });

        return $this->cards->find($card->id) ?? $card;
    }

    /**
     * Copies a card to the end of a list, on its own board or another one
     * the actor may edit. The copy is a plain, open card. $keep picks what
     * comes along (COPY_PARTS); comments, attachments, votes and history
     * never do, and members only if they can see the target board.
     *
     * @param list<string> $keep
     */
    public function copyCard(int $actorId, CardSummary $card, int $toBoardId, int $toListId, string $title, array $keep): CardSummary
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::ViewBoard);
        $this->gate->authorize($actorId, $toBoardId, Permission::EditCards);
        $this->assertOpenList($toBoardId, $toListId);
        $this->assertValid(['title' => $title], ['title' => ['Title', 'required|max:' . self::TITLE_MAX]]);
        $keep = array_values(array_intersect(self::COPY_PARTS, $keep));
        $has = static fn (string $part): bool => in_array($part, $keep, true);

        return $this->transactions->run(function () use ($actorId, $card, $toBoardId, $toListId, $title, $keep, $has): CardSummary {
            $copy = $this->cards->create($toBoardId, $toListId, trim($title), $this->cards->positionAtEnd($toListId), $actorId, [
                'description' => $has('description') ? $card->description : null,
                'due_at'      => $has('dates') ? $card->dueAt : null,
            ]);
            if ($has('dates') && $card->startAt !== null) {
                $this->cards->update($copy->id, ['start_at' => $card->startAt]);
            }
            if ($has('members')) {
                foreach ($this->cards->membersOf($card->id) as $memberId) {
                    if ($this->gate->can($memberId, $toBoardId, Permission::ViewBoard)) {
                        $this->cards->addMember($copy->id, $memberId);
                    }
                }
            }

            $this->events->publish(new CardCreated($copy->id, $toBoardId, $toListId, $actorId, $copy->title, $this->clock->now()));
            $this->events->publish(new CardCopied($card->id, $copy->id, $toBoardId, $actorId, $this->clock->now(), array_values(array_intersect(CardCopied::PARTS, $keep))));

            return $this->cards->find($copy->id);
        });
    }

    /** @return list<int> user ids on the card */
    public function membersOf(CardSummary $card): array
    {
        return $this->cards->membersOf($card->id);
    }

    /**
     * Assigns $memberId to the card, or takes them off if already on it.
     * Only people who can see the board can be assigned.
     */
    public function toggleMember(int $actorId, CardSummary $card, int $memberId): bool
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::EditCards);
        $this->assertBoardOpen($card->boardId);

        return $this->transactions->run(function () use ($actorId, $card, $memberId): bool {
            $added = !$this->cards->removeMember($card->id, $memberId);

            if ($added) {
                if (!$this->gate->can($memberId, $card->boardId, Permission::ViewBoard)) {
                    throw new ValidationException(new ValidationResult(['member' => 'That person can\'t see this board.']));
                }
                $this->cards->addMember($card->id, $memberId);
            }

            $this->events->publish(new CardMemberChanged($card->id, $card->boardId, $actorId, $memberId, $added, $card->title, $this->clock->now()));

            return $added;
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

    /** Moves the card to the bottom of a list (what automations do: "move it to Done"). */
    public function moveToEnd(int $actorId, CardSummary $card, int $toListId): CardSummary
    {
        if ($card->listId === $toListId) {
            return $card;
        }

        $cards = $this->cards->openCardsByList($card->boardId)[$toListId] ?? [];
        $last = $cards !== [] ? end($cards)->id : null;

        return $this->move($actorId, $card, $toListId, $last);
    }

    /** Sets or clears only the due date (Y-m-d or null), keeping everything else. */
    public function setDueDate(int $actorId, CardSummary $card, ?string $date): void
    {
        $this->update($actorId, $card, [
            'title'       => $card->title,
            'description' => (string) $card->description,
            'due_date'    => (string) $date,
        ] + ($date !== null && $card->startDate() !== null && $card->startDate() > $date ? ['start_date' => $date] : []));
    }

    /**
     * Reorders a list's open cards by due date (undated last) or by title,
     * writing fresh, evenly spread positions in one pass.
     */
    public function sortList(int $actorId, int $boardId, int $listId, string $by = 'due'): void
    {
        $this->gate->authorize($actorId, $boardId, Permission::MoveCards);
        $this->assertOpenList($boardId, $listId);
        $cards = $this->cards->openCardsByList($boardId)[$listId] ?? [];

        usort($cards, static fn (CardSummary $a, CardSummary $b): int => $by === 'title'
            ? strcmp(mb_strtolower($a->title), mb_strtolower($b->title))
            : (([$a->dueAt === null, (string) $a->dueAt] <=> [$b->dueAt === null, (string) $b->dueAt]) ?: strcmp($a->position, $b->position)));

        $this->transactions->run(function () use ($cards): void {
            foreach (array_map(null, $cards, FractionalIndex::spread(count($cards))) as [$card, $position]) {
                if ($card->position !== $position) {
                    $this->cards->update($card->id, ['position' => $position]);
                }
            }
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
