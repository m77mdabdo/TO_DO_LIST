<?php

declare(strict_types=1);

namespace App\Modules\Checklist\Contract;

use DateTimeImmutable;

final class ChecklistItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $checklistId,
        public readonly int $cardId,
        public readonly string $text,
        public readonly string $position,
        public readonly ?string $doneAt,
        public readonly ?int $assigneeId,
        public readonly ?string $dueAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['checklist_id'],
            (int) $row['card_id'],
            (string) $row['text'],
            (string) $row['position'],
            $row['done_at'] !== null ? (string) $row['done_at'] : null,
            $row['assignee_id'] !== null ? (int) $row['assignee_id'] : null,
            $row['due_at'] !== null ? (string) $row['due_at'] : null,
        );
    }

    public function isDone(): bool
    {
        return $this->doneAt !== null;
    }

    public function dueDate(): ?string
    {
        return $this->dueAt !== null ? substr($this->dueAt, 0, 10) : null;
    }

    public function isOverdue(DateTimeImmutable $today): bool
    {
        return !$this->isDone() && $this->dueAt !== null && $this->dueDate() < $today->format('Y-m-d');
    }
}
