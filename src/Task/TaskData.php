<?php

declare(strict_types=1);

namespace App\Task;

/**
 * The editable fields of a task, already cleaned up.
 */
final class TaskData
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $notes = null,
        public readonly Priority $priority = Priority::Normal,
        public readonly ?string $category = null,
        public readonly ?DueDate $dueDate = null,
    ) {
    }

    /**
     * Builds the data from already validated input.
     *
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        $text = static function (string $key) use ($input): ?string {
            $value = trim((string) ($input[$key] ?? ''));

            return $value === '' ? null : $value;
        };

        return new self(
            title: (string) $text('title'),
            notes: $text('notes'),
            priority: Priority::tryFrom((string) $text('priority')) ?? Priority::Normal,
            category: $text('category'),
            dueDate: ($due = $text('due_date')) ? DueDate::fromString($due) : null,
        );
    }
}
