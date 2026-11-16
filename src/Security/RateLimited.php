<?php

declare(strict_types=1);

namespace App\Security;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** A request was refused with HTTP 429. Identity writes it to the security log. */
final class RateLimited implements DomainEvent
{
    public function __construct(
        public readonly string $rule,
        public readonly ?int $userId,
        public readonly string $ip,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'security.rate_limited';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
