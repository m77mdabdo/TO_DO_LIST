<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract;

use DateTimeImmutable;

final class CardSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $boardId,
        public readonly int $listId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $position,
        public readonly ?string $dueAt,
        public readonly ?string $completedAt,
        public readonly bool $archived,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['public_id'],
            (int) $row['board_id'],
            (int) $row['list_id'],
            (string) $row['title'],
            $row['description'] !== null ? (string) $row['description'] : null,
            (string) $row['position'],
            $row['due_at'] !== null ? (string) $row['due_at'] : null,
            $row['completed_at'] !== null ? (string) $row['completed_at'] : null,
            $row['archived_at'] !== null,
            (string) $row['created_at'],
        );
    }

    public function isComplete(): bool
    {
        return $this->completedAt !== null;
    }

    /** Due date as Y-m-d, for date inputs and badges. */
    public function dueDate(): ?string
    {
        return $this->dueAt !== null ? substr($this->dueAt, 0, 10) : null;
    }

    public function isOverdue(DateTimeImmutable $today): bool
    {
        return !$this->isComplete() && $this->dueAt !== null && $this->dueDate() < $today->format('Y-m-d');
    }
}
