<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract;

/** Read-only card lookups. No access checks here: callers authorize with the AccessGate. */
interface CardQueries
{
    public function find(int $cardId): ?CardSummary;

    public function findByPublicId(string $publicId): ?CardSummary;

    /**
     * Every open card of a board in ONE query, grouped by list id in a hash
     * map (O(n)), so the board page never runs a query per list (no N+1).
     *
     * @return array<int, list<CardSummary>> list id => cards in order
     */
    public function openCardsByList(int $boardId): array;

    /**
     * Who is assigned to each card, in one query.
     *
     * @param list<int> $cardIds
     * @return array<int, list<int>> card id => user ids
     */
    public function membersForCards(array $cardIds): array;

    /** @return list<CardSummary> */
    public function archivedCards(int $boardId): array;
}
