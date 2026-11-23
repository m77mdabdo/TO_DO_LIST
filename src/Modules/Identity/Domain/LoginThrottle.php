<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Slows down password guessing. Failures are counted per account on one IP,
 * per account across all IPs, and per IP across all accounts, so neither one
 * attacker nor a crowd of IPs gets unlimited guesses, and people sharing an
 * office IP are not locked out by someone else's typos.
 */
interface LoginThrottle
{
    /** True when signing in to this email from this IP must wait. */
    public function tooManyAttempts(string $ip, string $email): bool;

    public function recordFailure(string $ip, string $email): void;

    /** After a correct password: forgets this account's failures, and only those. */
    public function clear(string $email): void;

    /** Deletes attempts older than the lockout window. Returns how many went. */
    public function prune(): int;
}
