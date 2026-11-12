<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\LoginThrottle;
use App\Support\Clock;
use PDO;

/**
 * Locks an IP out for a while after too many failed logins.
 */
final class PdoLoginThrottle implements LoginThrottle
{
    public function __construct(
        private PDO $db,
        private Clock $clock,
        private int $maxAttempts = 5,
        private int $lockSeconds = 900,
    ) {
    }

    public function tooManyAttempts(string $ip): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?');
        $stmt->execute([$ip, $this->clock->now()->getTimestamp() - $this->lockSeconds]);

        return (int) $stmt->fetchColumn() >= $this->maxAttempts;
    }

    public function recordFailure(string $ip, string $email): void
    {
        $this->db->prepare('INSERT INTO login_attempts (ip, email, attempted_at) VALUES (?, ?, ?)')
            ->execute([$ip, mb_strtolower($email), $this->clock->now()->getTimestamp()]);
    }

    public function clear(string $ip): void
    {
        $this->db->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
    }
}
