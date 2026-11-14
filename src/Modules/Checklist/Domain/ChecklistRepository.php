<?php

declare(strict_types=1);

namespace App\Modules\Checklist\Domain;

use App\Modules\Checklist\Contract\Checklist;
use App\Modules\Checklist\Contract\ChecklistItem;

interface ChecklistRepository
{
    /** @return list<Checklist> the card's checklists in order, each with its items in order (two queries) */
    public function forCard(int $cardId): array;

    public function find(int $id): ?Checklist;

    public function countOnCard(int $cardId): int;

    public function create(int $cardId, string $title, int $createdBy): int;

    public function rename(int $id, string $title): void;

    public function delete(int $id): void;

    public function findItem(int $id): ?ChecklistItem;

    public function countItems(int $checklistId): int;

    public function addItem(int $checklistId, int $cardId, string $text, int $createdBy): int;

    /** @param array{text?: string, assignee_id?: ?int, due_at?: ?string, done_at?: ?string} $fields */
    public function updateItem(int $id, array $fields): void;

    /** Puts the item into $checklistId right after $afterId (first when null). */
    public function moveItem(int $id, int $checklistId, ?int $afterId): void;

    public function deleteItem(int $id): void;

    /**
     * Done and total items per card, in one grouped query.
     *
     * @param list<int> $cardIds
     * @return array<int, array{done: int, total: int}>
     */
    public function progressForCards(array $cardIds): array;

    /**
     * Items on these cards that are due between two dates (Y-m-d, inclusive).
     *
     * @param list<int> $cardIds
     * @return list<ChecklistItem>
     */
    public function itemsDueBetween(array $cardIds, string $from, string $to): array;

    /**
     * Unfinished items given to a person on open cards, soonest due first
     * (uses the assignee index).
     *
     * @return list<ChecklistItem>
     */
    public function openItemsAssignedTo(int $userId, int $limit = 200): array;
}
