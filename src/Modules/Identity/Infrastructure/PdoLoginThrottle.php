<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\LoginThrottle;
use App\Support\Clock;
use PDO;

/**
 * Counts failed logins in login_attempts over a sliding window. Any one of the
 * three limits being reached blocks the attempt until older failures age out.
 */
final class PdoLoginThrottle implements LoginThrottle
{
    /** Matches login_attempts.email, so very long input can't fail the insert. */
    private const EMAIL_MAX = 190;

    public function __construct(
        private PDO $db,
        private Clock $clock,
        private int $maxPerAccountAndIp = 5,
        private int $maxPerAccount = 20,
        private int $maxPerIp = 50,
        private int $lockSeconds = 900,
    ) {
    }

    public function tooManyAttempts(string $ip, string $email): bool
    {
        $email = self::normalize($email);

        // One pass over the window, using the (ip, attempted_at) and (email, attempted_at) indexes.
        $stmt = $this->db->prepare(
            'SELECT
                COALESCE(SUM(CASE WHEN email = ? AND ip = ? THEN 1 ELSE 0 END), 0) AS pair,
                COALESCE(SUM(CASE WHEN email = ? THEN 1 ELSE 0 END), 0) AS account,
                COALESCE(SUM(CASE WHEN ip = ? THEN 1 ELSE 0 END), 0) AS from_ip
             FROM login_attempts
             WHERE attempted_at > ? AND (email = ? OR ip = ?)'
        );
        $stmt->execute([$email, $ip, $email, $ip, $this->windowStart(), $email, $ip]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return (int) ($counts['pair'] ?? 0) >= $this->maxPerAccountAndIp
            || (int) ($counts['account'] ?? 0) >= $this->maxPerAccount
            || (int) ($counts['from_ip'] ?? 0) >= $this->maxPerIp;
    }

    public function recordFailure(string $ip, string $email): void
    {
        $this->db->prepare('INSERT INTO login_attempts (ip, email, attempted_at) VALUES (?, ?, ?)')
            ->execute([$ip, self::normalize($email), $this->clock->now()->getTimestamp()]);
    }

    public function clear(string $email): void
    {
        $this->db->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([self::normalize($email)]);
    }

    public function prune(): int
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE attempted_at <= ?');
        $stmt->execute([$this->windowStart()]);

        return $stmt->rowCount();
    }

    private function windowStart(): int
    {
        return $this->clock->now()->getTimestamp() - $this->lockSeconds;
    }

    private static function normalize(string $email): string
    {
        return mb_substr(mb_strtolower(trim($email)), 0, self::EMAIL_MAX);
    }
}
