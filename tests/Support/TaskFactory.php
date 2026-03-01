<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Task\Task;

final class TaskFactory
{
    private static int $id = 0;

    /** @param array<string, mixed> $overrides */
    public static function make(array $overrides = []): Task
    {
        return Task::fromRow($overrides + [
            'id'           => ++self::$id,
            'user_id'      => 1,
            'title'        => 'Task ' . self::$id,
            'notes'        => null,
            'status'       => 'todo',
            'priority'     => 'normal',
            'category'     => null,
            'due_date'     => null,
            'created_at'   => '2026-01-10 09:00:00',
            'updated_at'   => '2026-01-10 09:00:00',
            'completed_at' => null,
        ]);
    }
}
