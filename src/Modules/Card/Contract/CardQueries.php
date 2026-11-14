<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract;

/** Read-only card lookups. No access checks here: callers authorize with the AccessGate. */
interface CardQueries
{
    public function find(int $cardId): ?CardSummary;

    /**
     * @param list<int> $cardIds
     * @return array<int, CardSummary> by id
     */
    public function findMany(array $cardIds): array;

    /**
     * Open (not archived) cards on these boards that start or are due between
     * two dates (Y-m-d, inclusive), for calendars.
     *
     * @param list<int> $boardIds
     * @return list<CardSummary>
     */
    public function scheduledBetween(array $boardIds, string $from, string $to): array;

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

    /**
     * Open cards on these boards whose title or description contains $text,
     * most recently changed first. The caller passes only boards the user may see.
     *
     * @param list<int> $boardIds
     * @return list<CardSummary>
     */
    public function search(array $boardIds, string $text, int $limit = 50): array;

    /**
     * Open, unfinished cards due between two dates (Y-m-d, both included),
     * for due-date reminders. Uses the due_at index.
     *
     * @return list<CardSummary>
     */
    public function dueBetween(string $from, string $to): array;

    /** @return list<CardSummary> */
    public function archivedCards(int $boardId): array;

    /**
     * Open, unfinished cards the person is assigned to, on any board, soonest
     * due first. The caller drops boards the person can no longer see.
     *
     * @return list<CardSummary>
     */
    public function openCardsAssignedTo(int $userId, int $limit = 300): array;

    /**
     * Open (not archived) cards of many boards in one query, soonest due first.
     *
     * @param list<int> $boardIds
     * @return list<CardSummary>
     */
    public function openCardsOnBoards(array $boardIds, int $limit = 1000): array;

    /**
     * Cards created (or completed) between two dates, Y-m-d inclusive, archived ones included: for reports.
     *
     * @param list<int> $boardIds
     * @param 'created_at'|'completed_at' $column
     * @return list<CardSummary>
     */
    public function cardsBetween(array $boardIds, string $column, string $from, string $to): array;
}
