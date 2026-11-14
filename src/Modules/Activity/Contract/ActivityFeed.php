<?php

declare(strict_types=1);

namespace App\Modules\Activity\Contract;

/**
 * Newest-first activity, paged with a keyset cursor: pass the id of the
 * last entry you showed as $beforeId to get the next page.
 */
interface ActivityFeed
{
    /** @return list<ActivityEntry> */
    public function forBoard(int $boardId, ?int $beforeId = null, int $limit = 30): array;

    /** @return list<ActivityEntry> */
    public function forCard(int $cardId, ?int $beforeId = null, int $limit = 30): array;

    /**
     * How many things each person did on these boards between two dates
     * (Y-m-d, inclusive), for team reports.
     *
     * @param list<int> $boardIds
     * @return array<int, int> user id => count, busiest first
     */
    public function countsByActor(array $boardIds, string $from, string $to): array;
}
