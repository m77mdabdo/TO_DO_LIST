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
}
