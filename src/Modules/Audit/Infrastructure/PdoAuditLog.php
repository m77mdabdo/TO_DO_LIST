<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure;

use App\Modules\Audit\Contract\AuditEntry;
use App\Modules\Audit\Domain\AuditLog;
use App\Support\Clock;
use PDO;

final class PdoAuditLog implements AuditLog
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    /** @param array<string, string> $details */
    public function add(int $workspaceId, ?int $actorId, string $action, string $target, array $details = []): void
    {
        $this->db->prepare('INSERT INTO audit_log (workspace_id, actor_id, action, target, details, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$workspaceId, $actorId, $action, mb_substr($target, 0, 190), json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $this->clock->now()->format('Y-m-d H:i:s')]);
    }

    /** @return list<AuditEntry> newest first */
    public function forWorkspace(int $workspaceId, ?int $beforeId, int $limit): array
    {
        $stmt = $this->db->prepare('SELECT id, actor_id, action, target, details, created_at FROM audit_log WHERE workspace_id = ?'
            . ($beforeId !== null ? ' AND id < ?' : '') . ' ORDER BY id DESC LIMIT ' . max(1, $limit));
        $stmt->execute($beforeId !== null ? [$workspaceId, $beforeId] : [$workspaceId]);

        return array_map(static function (array $row): AuditEntry {
            $details = json_decode((string) $row['details'], true);

            return new AuditEntry(
                (int) $row['id'],
                $row['actor_id'] !== null ? (int) $row['actor_id'] : null,
                (string) $row['action'],
                (string) $row['target'],
                is_array($details) ? array_map('strval', $details) : [],
                (string) $row['created_at'],
            );
        }, $stmt->fetchAll());
    }
}
