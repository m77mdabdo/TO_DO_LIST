<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure;

use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Support\Clock;
use PDO;

final class PdoBoardMembers implements BoardMembers
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function add(int $boardId, int $userId, BoardRole $role): void
    {
        // Re-adding someone updates their role instead of failing on the primary key.
        $this->db->prepare('DELETE FROM board_members WHERE board_id = ? AND user_id = ?')->execute([$boardId, $userId]);
        $this->db->prepare('INSERT INTO board_members (board_id, user_id, role, added_at) VALUES (?, ?, ?, ?)')
            ->execute([$boardId, $userId, $role->value, $this->clock->now()->format('Y-m-d H:i:s')]);
    }

    public function role(int $boardId, int $userId): ?BoardRole
    {
        $stmt = $this->db->prepare('SELECT role FROM board_members WHERE board_id = ? AND user_id = ?');
        $stmt->execute([$boardId, $userId]);
        $role = $stmt->fetchColumn();

        return $role === false ? null : BoardRole::from((string) $role);
    }

    public function boardIdsOf(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT board_id FROM board_members WHERE user_id = ?');
        $stmt->execute([$userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function membersOf(int $boardId): array
    {
        $stmt = $this->db->prepare('SELECT user_id, role FROM board_members WHERE board_id = ? ORDER BY added_at, user_id');
        $stmt->execute([$boardId]);

        $members = [];
        foreach ($stmt->fetchAll() as $row) {
            $members[(int) $row['user_id']] = BoardRole::from((string) $row['role']);
        }

        return $members;
    }
}
