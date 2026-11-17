<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract;

/**
 * Changing cards for a user from other modules (imports, the API). Every
 * method checks the user's permission on the card's board.
 */
interface CardWriter
{
    /**
     * @param array{description?: ?string, start_date?: ?string, due_date?: ?string, completed_at?: ?string, created_at?: ?string} $extra
     *        start_date and due_date are Y-m-d; completed_at and created_at are Y-m-d H:i:s
     */
    public function createCard(int $actorId, int $boardId, int $listId, string $title, array $extra = []): CardSummary;

    /** Puts a person on the card, if they can see its board. Returns whether they were added. */
    public function assign(int $actorId, CardSummary $card, int $memberId): bool;

    /**
     * @param array<string, mixed> $input title (required), description, start_date, due_date (Y-m-d or ""; missing start_date keeps it)
     * @throws \App\Validation\ValidationException
     */
    public function update(int $actorId, CardSummary $card, array $input): void;

    public function setComplete(int $actorId, CardSummary $card, bool $complete): void;

    /** Moves the card to the end of a list of its board. */
    public function moveToEnd(int $actorId, CardSummary $card, int $toListId): CardSummary;

    public function archive(int $actorId, CardSummary $card, bool $archive = true): void;
}
