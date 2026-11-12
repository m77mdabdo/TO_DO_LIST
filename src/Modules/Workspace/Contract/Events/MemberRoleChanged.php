<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contract\Events;

use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** A member's workspace role changed. */
final class MemberRoleChanged implements DomainEvent
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $userId,
        public readonly WorkspaceRole $from,
        public readonly WorkspaceRole $to,
        public readonly int $actorId,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'workspace.member_role_changed';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
