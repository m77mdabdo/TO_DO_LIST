<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * A person deleted their account. Published before the account is emptied,
 * so listeners can still read the email. Modules remove the person's
 * memberships, settings and personal records; shared work (cards, comments)
 * stays, credited to "Deleted user".
 */
final class AccountDeleted implements DomainEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'identity.account_deleted';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
