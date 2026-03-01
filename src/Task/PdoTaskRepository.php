<?php

declare(strict_types=1);

namespace App\Task;

use App\Support\Clock;
use PDO;

final class PdoTaskRepository implements TaskRepository
{
    private const DATETIME = 'Y-m-d H:i:s';

    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function allFor(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE user_id = ?');
        $stmt->execute([$userId]);

        return array_map(Task::fromRow(...), $stmt->fetchAll());
    }

    public function find(int $userId, int $id): ?Task
    {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row ? Task::fromRow($row) : null;
    }

    public function categories(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT category FROM tasks
             WHERE user_id = ? AND category IS NOT NULL AND category <> ''
             ORDER BY category"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function create(int $userId, TaskData $data): Task
    {
        $now = $this->now();

        $stmt = $this->db->prepare(
            'INSERT INTO tasks (user_id, title, notes, priority, category, due_date, status, created_at, updated_at)
             VALUES (:user_id, :title, :notes, :priority, :category, :due_date, :status, :created_at, :updated_at)'
        );
        $stmt->execute($this->columns($data) + [
            'user_id'    => $userId,
            'status'     => Status::Todo->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->find($userId, (int) $this->db->lastInsertId());
    }

    public function update(int $userId, int $id, TaskData $data): ?Task
    {
        $stmt = $this->db->prepare(
            'UPDATE tasks
             SET title = :title, notes = :notes, priority = :priority, category = :category,
                 due_date = :due_date, updated_at = :updated_at
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute($this->columns($data) + ['updated_at' => $this->now(), 'id' => $id, 'user_id' => $userId]);

        return $this->find($userId, $id);
    }

    public function move(int $userId, int $id, Status $status): ?Task
    {
        $stmt = $this->db->prepare(
            'UPDATE tasks SET status = :status, completed_at = :completed_at, updated_at = :updated_at
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'status'       => $status->value,
            'completed_at' => $status === Status::Done ? $this->now() : null,
            'updated_at'   => $this->now(),
            'id'           => $id,
            'user_id'      => $userId,
        ]);

        return $this->find($userId, $id);
    }

    public function delete(int $userId, int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function deleteDone(int $userId): int
    {
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE user_id = ? AND status = ?');
        $stmt->execute([$userId, Status::Done->value]);

        return $stmt->rowCount();
    }

    /** @return array<string, string|null> */
    private function columns(TaskData $data): array
    {
        return [
            'title'    => $data->title,
            'notes'    => $data->notes,
            'priority' => $data->priority->value,
            'category' => $data->category,
            'due_date' => $data->dueDate?->toString(),
        ];
    }

    private function now(): string
    {
        return $this->clock->now()->format(self::DATETIME);
    }
}
