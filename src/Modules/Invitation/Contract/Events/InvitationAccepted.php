<?php

declare(strict_types=1);

namespace App\Modules\Invitation\Contract\Events;

use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** Someone used an invitation link. $inviterId sent it. */
final class InvitationAccepted implements DomainEvent
{
    public function __construct(
        public readonly int $invitationId,
        public readonly int $workspaceId,
        public readonly int $userId,
        public readonly string $email,
        public readonly WorkspaceRole $role,
        public readonly int $inviterId,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'invitation.accepted';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
