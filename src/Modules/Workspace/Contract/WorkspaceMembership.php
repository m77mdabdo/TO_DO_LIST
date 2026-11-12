<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contract;

/**
 * Joining a workspace from outside the Workspace module (invitations).
 * The rules about who may give which role live here, in one place.
 */
interface WorkspaceMembership
{
    public function describe(int $workspaceId): ?WorkspaceInfo;

    /** May $actorId invite people, or change members, in this workspace? */
    public function canManageMembers(int $actorId, int $workspaceId): bool;

    /** May $actorId give $role to someone in this workspace? */
    public function mayGrant(int $actorId, int $workspaceId, WorkspaceRole $role): bool;

    /**
     * Adds the user with $role, or raises their role when $role is higher
     * than the one they hold. Never lowers a role and never duplicates a
     * membership. Returns the role they end up with.
     */
    public function join(int $workspaceId, int $userId, WorkspaceRole $role, int $actorId): WorkspaceRole;

    /** Makes this the workspace the user sees next. */
    public function open(int $userId, int $workspaceId): void;
}
