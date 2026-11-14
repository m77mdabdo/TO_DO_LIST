<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contract\Events;

use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** Someone left the workspace or was removed from it ($actorId === $userId when they left). */
final class MemberRemoved implements DomainEvent
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $userId,
        public readonly WorkspaceRole $role,
        public readonly int $actorId,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'workspace.member_removed';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
