<?php

declare(strict_types=1);

namespace App\Modules\Board\Infrastructure;

use App\Modules\Board\Domain\BoardVersions;
use App\Support\Clock;
use PDO;
use PDOException;

final class PdoBoardVersions implements BoardVersions
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function bump(int $boardId, ?int $actorId): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $update = $this->db->prepare('UPDATE board_versions SET version = version + 1, actor_id = ?, changed_at = ? WHERE board_id = ?');
        $update->execute([$actorId, $now, $boardId]);
        if ($update->rowCount() > 0) {
            return;
        }

        try {
            $this->db->prepare('INSERT INTO board_versions (board_id, version, actor_id, changed_at) VALUES (?, 1, ?, ?)')->execute([$boardId, $actorId, $now]);
        } catch (PDOException) {
            // Someone else inserted the row first, or the board was just deleted: try the update once more.
            $update->execute([$actorId, $now, $boardId]);
        }
    }

    public function current(int $boardId): array
    {
        $stmt = $this->db->prepare('SELECT version, actor_id, changed_at FROM board_versions WHERE board_id = ?');
        $stmt->execute([$boardId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row
            ? ['version' => (int) $row['version'], 'actorId' => $row['actor_id'] !== null ? (int) $row['actor_id'] : null, 'changedAt' => (string) $row['changed_at']]
            : ['version' => 0, 'actorId' => null, 'changedAt' => null];
    }
}
