<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** A new account was created. Other modules may set things up for it. */
final class UserRegistered implements DomainEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly string $name,
        public readonly string $email,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'identity.user_registered';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
