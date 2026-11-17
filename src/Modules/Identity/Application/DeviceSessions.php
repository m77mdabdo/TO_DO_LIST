<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Support\Clock;
use PDO;

/**
 * The browsers a person is signed in on. Each session holds a random secret;
 * the table keeps its hash with the IP and browser it was last seen from.
 * Deleting a row signs that browser out on its next request.
 */
final class DeviceSessions
{
    /** last_seen_at is written at most this often per session. */
    private const TOUCH_SECONDS = 300;

    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    /** @return string the secret to keep in the session */
    public function start(int $userId, string $ip, string $agent): string
    {
        $secret = bin2hex(random_bytes(32));
        $now = $this->now();
        $this->db->prepare('INSERT INTO user_sessions (user_id, token_hash, ip, user_agent, created_at, last_seen_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId, hash('sha256', $secret), mb_substr($ip, 0, 45), mb_substr($agent, 0, 255), $now, $now]);

        return $secret;
    }

    /** Is this session still on the list? Also notes when it was last seen. */
    public function check(int $userId, string $secret): bool
    {
        $stmt = $this->db->prepare('SELECT id, last_seen_at FROM user_sessions WHERE token_hash = ? AND user_id = ?');
        $stmt->execute([hash('sha256', $secret), $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return false;
        }
        $now = $this->clock->now();
        if ((string) $row['last_seen_at'] < $now->modify('-' . self::TOUCH_SECONDS . ' seconds')->format('Y-m-d H:i:s')) {
            $this->db->prepare('UPDATE user_sessions SET last_seen_at = ? WHERE id = ?')->execute([$now->format('Y-m-d H:i:s'), (int) $row['id']]);
        }

        return true;
    }

    /** @return list<array{id: int, ip: string, agent: string, createdAt: string, lastSeenAt: string, current: bool}> */
    public function forUser(int $userId, ?string $currentSecret): array
    {
        $stmt = $this->db->prepare('SELECT id, token_hash, ip, user_agent, created_at, last_seen_at FROM user_sessions WHERE user_id = ? ORDER BY last_seen_at DESC, id DESC');
        $stmt->execute([$userId]);
        $current = $currentSecret !== null ? hash('sha256', $currentSecret) : null;

        return array_map(static fn (array $r) => [
            'id'         => (int) $r['id'],
            'ip'         => (string) $r['ip'],
            'agent'      => (string) $r['user_agent'],
            'createdAt'  => (string) $r['created_at'],
            'lastSeenAt' => (string) $r['last_seen_at'],
            'current'    => $current !== null && hash_equals((string) $r['token_hash'], $current),
        ], $stmt->fetchAll());
    }

    public function end(int $userId, int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM user_sessions WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function endSecret(string $secret): void
    {
        $this->db->prepare('DELETE FROM user_sessions WHERE token_hash = ?')->execute([hash('sha256', $secret)]);
    }

    /** Every session but $keepSecret's (all of them when null). @return int how many ended */
    public function endAll(int $userId, ?string $keepSecret = null): int
    {
        $stmt = $keepSecret === null
            ? $this->db->prepare('DELETE FROM user_sessions WHERE user_id = ?')
            : $this->db->prepare('DELETE FROM user_sessions WHERE user_id = ? AND token_hash <> ?');
        $stmt->execute($keepSecret === null ? [$userId] : [$userId, hash('sha256', $keepSecret)]);

        return $stmt->rowCount();
    }

    /** Sessions nobody used for $days (run by bin/cleanup.php). */
    public function prune(int $days = 30): int
    {
        $stmt = $this->db->prepare('DELETE FROM user_sessions WHERE last_seen_at < ?');
        $stmt->execute([$this->clock->now()->modify("-$days days")->format('Y-m-d H:i:s')]);

        return $stmt->rowCount();
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
