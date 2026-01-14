<?php

namespace Classes;

use PDO;

/*
 | Every query is scoped by user_id, so one user can never
 | read or change another user's tasks by guessing an id.
 */
final class TaskRepository
{
    public const STATUSES   = ['todo' => 'To do', 'doing' => 'Doing', 'done' => 'Done'];
    public const PRIORITIES = ['high' => 'High', 'normal' => 'Normal', 'low' => 'Low'];

    private const FIELDS = ['title', 'notes', 'priority', 'category', 'due_date'];

    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, array<int, array>> tasks grouped by status */
    public function board(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM tasks WHERE user_id = ?
             ORDER BY CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END,
                      CASE WHEN due_date IS NULL THEN 1 ELSE 0 END,
                      due_date,
                      id DESC"
        );
        $stmt->execute([$userId]);

        $board = array_fill_keys(array_keys(self::STATUSES), []);

        foreach ($stmt as $task) {
            $board[$task['status']][] = $task;
        }

        // finished work reads best newest-first
        usort($board['done'], fn ($a, $b) => strcmp((string) $b['completed_at'], (string) $a['completed_at']));

        return $board;
    }

    public function stats(array $board): array
    {
        $today = date('Y-m-d');
        $open = array_merge($board['todo'], $board['doing']);

        $total = count($open) + count($board['done']);

        return [
            'total'      => $total,
            'open'       => count($open),
            'done'       => count($board['done']),
            'overdue'    => count(array_filter($open, fn ($t) => $t['due_date'] && $t['due_date'] < $today)),
            'today'      => count(array_filter($open, fn ($t) => $t['due_date'] === $today)),
            'done_today' => count(array_filter($board['done'], fn ($t) => str_starts_with((string) $t['completed_at'], $today))),
            'percent'    => $total ? (int) round(count($board['done']) / $total * 100) : 0,
        ];
    }

    public function categories(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT category FROM tasks WHERE user_id = ? AND category IS NOT NULL AND category <> '' ORDER BY category"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function find(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        return $stmt->fetch() ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $data = $this->clean($data);

        $stmt = $this->db->prepare(
            'INSERT INTO tasks (user_id, title, notes, priority, category, due_date, status, created_at, updated_at)
             VALUES (:user_id, :title, :notes, :priority, :category, :due_date, :status, :now, :now2)'
        );
        $stmt->execute($data + [
            'user_id' => $userId,
            'status'  => 'todo',
            'now'     => now(),
            'now2'    => now(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $userId, int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE tasks
             SET title = :title, notes = :notes, priority = :priority, category = :category,
                 due_date = :due_date, updated_at = :now
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute($this->clean($data) + ['now' => now(), 'id' => $id, 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    public function move(int $userId, int $id, string $status): bool
    {
        if (!isset(self::STATUSES[$status])) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE tasks SET status = :status, completed_at = :completed, updated_at = :now
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'status'    => $status,
            'completed' => $status === 'done' ? now() : null,
            'now'       => now(),
            'id'        => $id,
            'user_id'   => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $userId, int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function clearDone(int $userId): int
    {
        $stmt = $this->db->prepare("DELETE FROM tasks WHERE user_id = ? AND status = 'done'");
        $stmt->execute([$userId]);

        return $stmt->rowCount();
    }

    private function clean(array $data): array
    {
        $out = [];

        foreach (self::FIELDS as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $out[$field] = $value === '' && $field !== 'title' ? null : $value;
        }

        $out['priority'] ??= 'normal';

        return $out;
    }
}
