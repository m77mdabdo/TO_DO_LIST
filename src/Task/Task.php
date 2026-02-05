<?php

declare(strict_types=1);

namespace App\Task;

use DateTimeImmutable;

/**
 * One card on the board. Immutable: changes go through the repository.
 */
final class Task
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $title,
        public readonly ?string $notes,
        public readonly Status $status,
        public readonly Priority $priority,
        public readonly ?string $category,
        public readonly ?DueDate $dueDate,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?DateTimeImmutable $completedAt = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            title: (string) $row['title'],
            notes: $row['notes'] ?: null,
            status: Status::from((string) $row['status']),
            priority: Priority::from((string) $row['priority']),
            category: $row['category'] ?: null,
            dueDate: $row['due_date'] ? DueDate::fromString(substr((string) $row['due_date'], 0, 10)) : null,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) $row['updated_at']),
            completedAt: $row['completed_at'] ? new DateTimeImmutable((string) $row['completed_at']) : null,
        );
    }

    public function isDone(): bool
    {
        return $this->status === Status::Done;
    }

    public function isOverdue(DateTimeImmutable $today): bool
    {
        return !$this->isDone() && $this->dueDate !== null && $this->dueDate->daysFrom($today) < 0;
    }

    public function isDueOn(DateTimeImmutable $today): bool
    {
        return !$this->isDone() && $this->dueDate !== null && $this->dueDate->daysFrom($today) === 0;
    }

    public function wasCompletedOn(DateTimeImmutable $today): bool
    {
        return $this->completedAt !== null && $this->completedAt->format('Y-m-d') === $today->format('Y-m-d');
    }

    /** Lower-cased text the board's search box matches against. */
    public function searchText(): string
    {
        return mb_strtolower(trim($this->title . ' ' . $this->notes . ' ' . $this->category));
    }
}
