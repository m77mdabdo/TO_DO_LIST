<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contract;

interface WorkspaceDirectory
{
    /** @return list<WorkspaceSummary> the user's workspaces, oldest membership first */
    public function workspacesOf(int $userId): array;

    /** The user's role there, or null when they aren't in it. Cached for the request. */
    public function roleOf(int $userId, int $workspaceId): ?WorkspaceRole;

    /** The workspace by public id, only if the user belongs to it (no existence leaks). */
    public function findForUser(string $publicId, int $userId): ?WorkspaceSummary;

    /**
     * The workspace the user is working in: the one they last switched to,
     * else their first. Null only for a user with no workspace yet.
     */
    public function current(int $userId): ?WorkspaceSummary;

    /** @return list<array{userId: int, role: WorkspaceRole}> */
    public function members(int $workspaceId): array;

    /** The workspace's rules (invitations, public links). */
    public function policy(int $workspaceId): WorkspacePolicy;

    /** True while an owner or admin has suspended the user in this workspace. */
    public function isSuspended(int $userId, int $workspaceId): bool;

    /**
     * Workspaces where this person is the only owner and others still work:
     * they must be handed over before the account can be deleted.
     *
     * @return list<WorkspaceSummary>
     */
    public function soleOwnerOf(int $userId): array;
}
