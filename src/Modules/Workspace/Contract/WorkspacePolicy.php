<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contract;

/** Rules the workspace owners set: who may invite, and whether boards may be shared by public link. */
final class WorkspacePolicy
{
    public function __construct(
        /** Full members may invite too (not only owners and admins). */
        public readonly bool $membersInvite = false,
        /** The role a member's invitation gives: member or guest. */
        public readonly WorkspaceRole $inviteRole = WorkspaceRole::Member,
        /** A member's invitation waits for an owner or admin before it is sent. */
        public readonly bool $inviteApproval = true,
        /** Board managers may share a board read-only through a secret link. */
        public readonly bool $publicLinks = false,
    ) {
    }
}
