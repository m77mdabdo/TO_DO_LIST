<?php

declare(strict_types=1);

namespace App\Modules\Checklist\Contract;

/** Read-only checklist lookups for other modules. No access checks: callers authorize. */
interface ChecklistQueries
{
    /**
     * Done and total items per card, in one query.
     *
     * @param list<int> $cardIds
     * @return array<int, array{done: int, total: int}>
     */
    public function progressForCards(array $cardIds): array;

    /** @return list<ChecklistItem> unfinished items given to the person, on cards that are not archived */
    public function openItemsAssignedTo(int $userId): array;
}
