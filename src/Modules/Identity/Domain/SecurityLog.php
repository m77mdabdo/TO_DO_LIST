<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Account security events (sign-ins, failed logins, password resets,
 * email confirmation, refused requests). Kept apart from the board
 * activity and the workspace audit log: only the account owner sees it.
 */
interface SecurityLog
{
    public const TYPES = [
        'registered', 'login', 'login_failed', 'logout',
        'password_reset_requested', 'password_reset', 'password_changed',
        'verification_sent', 'email_verified', 'rate_limited',
    ];

    public function record(string $type, ?int $userId, string $ip, string $detail = ''): void;

    /** @return list<array{type: string, ip: string, detail: string, created_at: string}> newest first */
    public function forUser(int $userId, int $limit = 50): array;

    /** Deletes events older than $before. */
    public function prune(string $before): int;
}
