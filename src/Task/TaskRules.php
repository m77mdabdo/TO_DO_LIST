<?php

declare(strict_types=1);

namespace App\Task;

/**
 * The validation rules for the add and edit forms, kept in one place
 * so both forms always accept exactly the same data.
 */
final class TaskRules
{
    public const TITLE_MAX    = 150;
    public const NOTES_MAX    = 1000;
    public const CATEGORY_MAX = 30;

    /** @return array<string, array{0: string, 1: string}> */
    public static function fields(): array
    {
        return [
            'title'    => ['Title', 'required|text|min:2|max:' . self::TITLE_MAX],
            'notes'    => ['Notes', 'max:' . self::NOTES_MAX],
            'priority' => ['Priority', 'in:' . implode(',', Priority::values())],
            'due_date' => ['Due date', 'date'],
            'category' => ['List name', 'max:' . self::CATEGORY_MAX],
        ];
    }
}
