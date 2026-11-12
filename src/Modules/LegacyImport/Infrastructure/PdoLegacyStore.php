<?php

declare(strict_types=1);

namespace App\Modules\LegacyImport\Infrastructure;

use App\Modules\LegacyImport\Domain\LegacyStore;
use App\Support\Clock;
use PDO;

final class PdoLegacyStore implements LegacyStore
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function pendingTasks(int $userId): array
    {
        // Anti-join on the map's primary key: only tasks with no mapping yet.
        $stmt = $this->db->prepare('SELECT t.id, t.title, t.notes, t.status, t.priority, t.category, t.due_date, t.completed_at, t.created_at
            FROM tasks t LEFT JOIN legacy_task_map m ON m.task_id = t.id
            WHERE t.user_id = ? AND m.task_id IS NULL
            ORDER BY t.created_at, t.id');
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function usersWithPendingTasks(): array
    {
        $stmt = $this->db->query('SELECT DISTINCT t.user_id FROM tasks t LEFT JOIN legacy_task_map m ON m.task_id = t.id WHERE m.task_id IS NULL ORDER BY t.user_id');

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function boardOf(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT lb.board_id, lb.todo_list_id, lb.doing_list_id, lb.done_list_id
            FROM legacy_boards lb JOIN boards b ON b.id = lb.board_id WHERE lb.user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return $row ? [
            'boardId' => (int) $row['board_id'],
            'todo'    => (int) $row['todo_list_id'],
            'doing'   => (int) $row['doing_list_id'],
            'done'    => (int) $row['done_list_id'],
        ] : null;
    }

    public function rememberBoard(int $userId, int $boardId, int $todo, int $doing, int $done): void
    {
        $this->db->prepare('DELETE FROM legacy_boards WHERE user_id = ?')->execute([$userId]);
        $this->db->prepare('INSERT INTO legacy_boards (user_id, board_id, todo_list_id, doing_list_id, done_list_id) VALUES (?, ?, ?, ?, ?)')
            ->execute([$userId, $boardId, $todo, $doing, $done]);
    }

    public function markImported(int $taskId, int $cardId, int $userId): void
    {
        $this->db->prepare('INSERT INTO legacy_task_map (task_id, card_id, user_id, imported_at) VALUES (?, ?, ?, ?)')
            ->execute([$taskId, $cardId, $userId, $this->clock->now()->format('Y-m-d H:i:s')]);
    }

    public function counts(?int $userId = null): array
    {
        $where = $userId !== null ? ' WHERE user_id = ?' : '';
        $params = $userId !== null ? [$userId] : [];

        $tasks = $this->db->prepare('SELECT COUNT(*) FROM tasks' . $where);
        $tasks->execute($params);
        $imported = $this->db->prepare('SELECT COUNT(*) FROM legacy_task_map' . $where);
        $imported->execute($params);

        return ['tasks' => (int) $tasks->fetchColumn(), 'imported' => (int) $imported->fetchColumn()];
    }
}
