<?php

declare(strict_types=1);

namespace App\Task;

/**
 * Tasks grouped into the three columns, in the order the board shows them.
 */
final class Board
{
    /** @var array<string, list<Task>> */
    private array $columns;

    /** @param iterable<Task> $tasks */
    public function __construct(iterable $tasks)
    {
        $this->columns = array_fill_keys(array_column(Status::cases(), 'value'), []);

        foreach ($tasks as $task) {
            $this->columns[$task->status->value][] = $task;
        }

        usort($this->columns[Status::Todo->value], $this->byPriorityThenDue(...));
        usort($this->columns[Status::Doing->value], $this->byPriorityThenDue(...));
        usort($this->columns[Status::Done->value], $this->byNewestCompleted(...));
    }

    /** @return list<Task> */
    public function column(Status $status): array
    {
        return $this->columns[$status->value];
    }

    /** @return list<Task> tasks that are not done yet */
    public function open(): array
    {
        return [...$this->column(Status::Todo), ...$this->column(Status::Doing)];
    }

    public function count(): int
    {
        return array_sum(array_map('count', $this->columns));
    }

    private function byPriorityThenDue(Task $a, Task $b): int
    {
        return [$a->priority->weight(), $a->dueDate === null, $a->dueDate?->toString(), -$a->id]
            <=> [$b->priority->weight(), $b->dueDate === null, $b->dueDate?->toString(), -$b->id];
    }

    private function byNewestCompleted(Task $a, Task $b): int
    {
        return $b->completedAt <=> $a->completedAt;
    }
}
