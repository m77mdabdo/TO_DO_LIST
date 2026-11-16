<?php

declare(strict_types=1);

namespace App\Security;

final class RateLimitResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $limit,
        public readonly int $remaining,
        /** Seconds until the next request is allowed; 0 when allowed. */
        public readonly int $retryAfter,
    ) {
    }
}
