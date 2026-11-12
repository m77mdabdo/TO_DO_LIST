<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contract;

/**
 * Read-only lookup of users for other modules (member lists, card
 * assignees, comment authors...).
 */
interface UserDirectory
{
    public function find(int $id): ?User;

    /**
     * One query for many ids, so callers render a list of N items with
     * a constant number of queries instead of N (no N+1).
     *
     * @param list<int> $ids
     * @return array<int, User> keyed by user id; unknown ids are left out
     */
    public function findMany(array $ids): array;
}
