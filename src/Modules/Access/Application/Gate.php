<?php

declare(strict_types=1);

namespace App\Modules\Access\Application;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Workspace\Contract\WorkspaceDirectory;

/**
 * Effective board role, in order:
 *   1. the role the user was given on the board;
 *   2. Manager, for owners and admins of the board's workspace;
 *   3. Viewer, for full workspace members on a workspace-visible board;
 *   4. nothing.
 *
 * Results are cached per request in a hash map keyed "user:board", so a
 * board page that checks permissions for every card still resolves the
 * role once (O(1) per check after the first).
 */
final class Gate implements AccessGate
{
    /** @var array<string, ?BoardRole> */
    private array $cache = [];

    public function __construct(
        private BoardMembers $members,
        private BoardDirectory $boards,
        private WorkspaceDirectory $workspaces,
    ) {
    }

    public function roleOn(int $userId, int $boardId): ?BoardRole
    {
        $key = $userId . ':' . $boardId;

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->resolve($userId, $boardId);
        }

        return $this->cache[$key];
    }

    public function can(int $userId, int $boardId, Permission $permission): bool
    {
        return $this->roleOn($userId, $boardId)?->allows($permission) ?? false;
    }

    public function authorize(int $userId, int $boardId, Permission $permission): BoardRole
    {
        $role = $this->roleOn($userId, $boardId);

        if ($role === null || !$role->allows(Permission::ViewBoard)) {
            throw AccessDenied::hidden();
        }

        if (!$role->allows($permission)) {
            throw AccessDenied::forbidden();
        }

        return $role;
    }

    /** Called after membership changes so later checks in this request see them. */
    public function forget(int $userId, int $boardId): void
    {
        unset($this->cache[$userId . ':' . $boardId]);
    }

    private function resolve(int $userId, int $boardId): ?BoardRole
    {
        $explicit = $this->members->role($boardId, $userId);

        if ($explicit === BoardRole::Manager) {
            return $explicit;
        }

        $board = $this->boards->find($boardId);

        if ($board === null) {
            return null;
        }

        $workspaceRole = $this->workspaces->roleOf($userId, $board->workspaceId);

        if ($workspaceRole?->managesAllBoards()) {
            return BoardRole::Manager;
        }

        if ($explicit !== null) {
            return $explicit;
        }

        if ($board->visibility === BoardVisibility::Workspace && $workspaceRole?->isFullMember()) {
            return BoardRole::Viewer;
        }

        return null;
    }
}
