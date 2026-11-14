<?php

declare(strict_types=1);

namespace App\Modules\Invitation\Contract\Events;

use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** An invitation was sent, or sent again with a new link ($resent). */
final class InvitationSent implements DomainEvent
{
    public function __construct(
        public readonly int $invitationId,
        public readonly int $workspaceId,
        public readonly string $email,
        public readonly WorkspaceRole $role,
        public readonly int $actorId,
        public readonly bool $resent,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'invitation.sent';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
