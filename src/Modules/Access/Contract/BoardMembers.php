<?php

declare(strict_types=1);

namespace App\Modules\Access\Contract;

interface BoardMembers
{
    public function add(int $boardId, int $userId, BoardRole $role): void;

    public function remove(int $boardId, int $userId): void;

    /** The role the user was explicitly given on the board, if any. */
    public function role(int $boardId, int $userId): ?BoardRole;

    /** @return list<int> ids of boards the user was explicitly added to */
    public function boardIdsOf(int $userId): array;

    /** @return array<int, BoardRole> user id => role */
    public function membersOf(int $boardId): array;
}
