<?php

declare(strict_types=1);

namespace App\Task;

/**
 * Storage for tasks. Every method takes the owner's id, so one user
 * can never read or change another user's tasks by guessing an id.
 */
interface TaskRepository
{
    /** @return list<Task> */
    public function allFor(int $userId): array;

    public function find(int $userId, int $id): ?Task;

    /** @return list<string> */
    public function categories(int $userId): array;

    public function create(int $userId, TaskData $data): Task;

    public function update(int $userId, int $id, TaskData $data): ?Task;

    public function move(int $userId, int $id, Status $status): ?Task;

    public function delete(int $userId, int $id): bool;

    /** @return int number of tasks removed */
    public function deleteDone(int $userId): int;
}
