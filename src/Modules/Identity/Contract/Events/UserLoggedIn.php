<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** A user signed in. Modules can catch up on work for that user (provisioning, imports). */
final class UserLoggedIn implements DomainEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly string $name,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'identity.user_logged_in';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
