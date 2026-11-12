<?php

declare(strict_types=1);

namespace App\Modules\Access\Contract;

/**
 * The single place that answers "may this user do that on this board?".
 * Every read and write of board data goes through it; hiding a button in
 * the UI is never the protection.
 */
interface AccessGate
{
    /** Effective role (explicit membership, workspace admin, or workspace-visible board), or null. */
    public function roleOn(int $userId, int $boardId): ?BoardRole;

    public function can(int $userId, int $boardId, Permission $permission): bool;

    /**
     * Everyone with an effective role on the board: its members, the
     * workspace owners and admins, and, on a workspace board, the full members.
     *
     * @return array<int, BoardRole> user id => role
     */
    public function peopleOn(int $boardId): array;

    /** @throws AccessDenied */
    public function authorize(int $userId, int $boardId, Permission $permission): BoardRole;
}
