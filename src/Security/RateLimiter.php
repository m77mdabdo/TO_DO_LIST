<?php

declare(strict_types=1);

namespace App\Security;

use App\Support\Clock;
use PDO;

/**
 * Counts requests per bucket in a sliding window, in the database so the
 * limit holds across PHP processes. A bucket is stored as a SHA-256 hash,
 * so no email address, IP or token ends up in the table.
 *
 * Each hit costs one indexed COUNT and one INSERT. A refused request is not
 * counted, so the window frees up on its own once the caller slows down.
 */
final class RateLimiter
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function attempt(string $bucket, int $max, int $seconds): RateLimitResult
    {
        $hash = hash('sha256', $bucket);
        $now = $this->clock->now()->getTimestamp();
        $since = $now - $seconds;

        // Old hits of this bucket are dead weight; dropping them keeps each bucket at most $max rows.
        $this->db->prepare('DELETE FROM rate_limit_hits WHERE bucket = ? AND hit_at <= ?')->execute([$hash, $since]);

        $stmt = $this->db->prepare('SELECT COUNT(*), MIN(hit_at) FROM rate_limit_hits WHERE bucket = ? AND hit_at > ?');
        $stmt->execute([$hash, $since]);
        [$count, $oldest] = $stmt->fetch(PDO::FETCH_NUM);
        $count = (int) $count;

        if ($count >= $max) {
            return new RateLimitResult(false, $max, 0, max(1, (int) $oldest + $seconds - $now));
        }

        $this->db->prepare('INSERT INTO rate_limit_hits (bucket, hit_at) VALUES (?, ?)')->execute([$hash, $now]);

        return new RateLimitResult(true, $max, $max - $count - 1, 0);
    }

    /** Forgets a bucket, e.g. after a successful login. */
    public function clear(string $bucket): void
    {
        $this->db->prepare('DELETE FROM rate_limit_hits WHERE bucket = ?')->execute([hash('sha256', $bucket)]);
    }

    /** Removes hits older than $seconds (bin/cleanup.php). Returns how many went. */
    public function prune(int $seconds = 86400): int
    {
        $stmt = $this->db->prepare('DELETE FROM rate_limit_hits WHERE hit_at <= ?');
        $stmt->execute([$this->clock->now()->getTimestamp() - $seconds]);

        return $stmt->rowCount();
    }
}
