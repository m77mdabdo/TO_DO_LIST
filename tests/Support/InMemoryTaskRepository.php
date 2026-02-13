<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Clock;
use App\Task\Status;
use App\Task\Task;
use App\Task\TaskData;
use App\Task\TaskRepository;

/**
 * A TaskRepository kept in an array, so service tests don't need a database.
 */
final class InMemoryTaskRepository implements TaskRepository
{
    /** @var array<int, Task> */
    private array $tasks = [];
    private int $nextId = 1;

    public function __construct(private Clock $clock)
    {
    }

    public function allFor(int $userId): array
    {
        return array_values(array_filter($this->tasks, fn (Task $t) => $t->userId === $userId));
    }

    public function find(int $userId, int $id): ?Task
    {
        $task = $this->tasks[$id] ?? null;

        return $task?->userId === $userId ? $task : null;
    }

    public function categories(int $userId): array
    {
        $names = array_unique(array_filter(array_map(fn (Task $t) => $t->category, $this->allFor($userId))));
        sort($names);

        return array_values($names);
    }

    public function create(int $userId, TaskData $data): Task
    {
        $id = $this->nextId++;

        return $this->tasks[$id] = $this->build($id, $userId, $data, Status::Todo, null);
    }

    public function update(int $userId, int $id, TaskData $data): ?Task
    {
        $task = $this->find($userId, $id);

        return $task ? $this->tasks[$id] = $this->build($id, $userId, $data, $task->status, $task->completedAt) : null;
    }

    public function move(int $userId, int $id, Status $status): ?Task
    {
        $task = $this->find($userId, $id);

        if (!$task) {
            return null;
        }

        $data = new TaskData($task->title, $task->notes, $task->priority, $task->category, $task->dueDate);

        return $this->tasks[$id] = $this->build($id, $userId, $data, $status, $status === Status::Done ? $this->clock->now() : null);
    }

    public function delete(int $userId, int $id): bool
    {
        if (!$this->find($userId, $id)) {
            return false;
        }

        unset($this->tasks[$id]);

        return true;
    }

    public function deleteDone(int $userId): int
    {
        $done = array_filter($this->allFor($userId), fn (Task $t) => $t->isDone());

        foreach ($done as $task) {
            unset($this->tasks[$task->id]);
        }

        return count($done);
    }

    private function build(int $id, int $userId, TaskData $data, Status $status, ?\DateTimeImmutable $completedAt): Task
    {
        return new Task(
            $id, $userId, $data->title, $data->notes, $status, $data->priority,
            $data->category, $data->dueDate, $this->clock->now(), $this->clock->now(), $completedAt,
        );
    }
}
