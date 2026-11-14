<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\LoginThrottle;
use App\Modules\Identity\Domain\SecurityLog;
use App\Modules\Identity\Domain\UserTokens;
use App\Support\Clock;

/**
 * Deletes what has no use any more (run daily by bin/cleanup.php):
 * used or expired links after a day, failed logins past the lockout,
 * and security events past the retention period.
 */
final class AccountCleanup
{
    public function __construct(
        private UserTokens $tokens,
        private LoginThrottle $throttle,
        private SecurityLog $log,
        private Clock $clock,
    ) {
    }

    /** @return array{tokens: int, login_attempts: int, security_events: int} */
    public function run(int $securityLogDays = 180): array
    {
        $now = $this->clock->now();

        return [
            'tokens'          => $this->tokens->prune($now->modify('-1 day')->format('Y-m-d H:i:s')),
            'login_attempts'  => $this->throttle->prune(),
            'security_events' => $this->log->prune($now->modify('-' . max(1, $securityLogDays) . ' days')->format('Y-m-d H:i:s')),
        ];
    }
}
