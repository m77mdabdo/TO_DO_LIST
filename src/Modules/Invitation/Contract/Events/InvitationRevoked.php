<?php

declare(strict_types=1);

namespace App\Modules\Invitation\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** An open invitation was cancelled; its link no longer works. */
final class InvitationRevoked implements DomainEvent
{
    public function __construct(
        public readonly int $invitationId,
        public readonly int $workspaceId,
        public readonly string $email,
        public readonly int $actorId,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'invitation.revoked';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
