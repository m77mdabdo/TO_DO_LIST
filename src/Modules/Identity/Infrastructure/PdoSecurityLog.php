<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\SecurityLog;
use App\Support\Clock;
use PDO;

final class PdoSecurityLog implements SecurityLog
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function record(string $type, ?int $userId, string $ip, string $detail = ''): void
    {
        $this->db->prepare('INSERT INTO security_events (user_id, type, ip, detail, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$userId, $type, mb_substr($ip, 0, 45), mb_substr($detail, 0, 255), $this->clock->now()->format('Y-m-d H:i:s')]);
    }

    public function forUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare('SELECT type, ip, detail, created_at FROM security_events WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)));
        $stmt->execute([$userId]);

        return array_map(static fn (array $row) => [
            'type'       => (string) $row['type'],
            'ip'         => (string) $row['ip'],
            'detail'     => (string) $row['detail'],
            'created_at' => (string) $row['created_at'],
        ], $stmt->fetchAll());
    }

    public function prune(string $before): int
    {
        $stmt = $this->db->prepare('DELETE FROM security_events WHERE created_at < ?');
        $stmt->execute([$before]);

        return $stmt->rowCount();
    }
}
