<?php

declare(strict_types=1);

namespace App\Task;

use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\Validator;

/**
 * Everything a user can do with their tasks. Controllers stay thin and
 * only translate HTTP to these calls.
 */
final class TaskService
{
    public function __construct(
        private TaskRepository $tasks,
        private Validator $validator,
        private Clock $clock,
    ) {
    }

    public function board(int $userId): Board
    {
        return new Board($this->tasks->allFor($userId));
    }

    public function stats(int $userId): BoardStats
    {
        return BoardStats::of($this->board($userId), $this->clock->now());
    }

    /** @return list<string> */
    public function categories(int $userId): array
    {
        return $this->tasks->categories($userId);
    }

    public function find(int $userId, int $id): Task
    {
        return $this->tasks->find($userId, $id) ?? throw new TaskNotFound();
    }

    /** @param array<string, mixed> $input */
    public function create(int $userId, array $input): Task
    {
        return $this->tasks->create($userId, $this->validated($input));
    }

    /** @param array<string, mixed> $input */
    public function update(int $userId, int $id, array $input): Task
    {
        $this->find($userId, $id);

        return $this->tasks->update($userId, $id, $this->validated($input)) ?? throw new TaskNotFound();
    }

    public function move(int $userId, int $id, Status $status): Task
    {
        return $this->tasks->move($userId, $id, $status) ?? throw new TaskNotFound();
    }

    public function delete(int $userId, int $id): void
    {
        if (!$this->tasks->delete($userId, $id)) {
            throw new TaskNotFound();
        }
    }

    public function clearDone(int $userId): int
    {
        return $this->tasks->deleteDone($userId);
    }

    /** @param array<string, mixed> $input */
    private function validated(array $input): TaskData
    {
        $input['priority'] = ($input['priority'] ?? '') ?: Priority::Normal->value;

        $result = $this->validator->validate($input, TaskRules::fields());

        if ($result->fails()) {
            throw new ValidationException($result);
        }

        return TaskData::fromArray($input);
    }
}
