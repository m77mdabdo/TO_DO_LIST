<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use Tests\Support\TaskFactory;
use Tests\TestCase;

final class TaskTest extends TestCase
{
    public function testFinishedTasksAreNeverOverdue(): void
    {
        $today = new DateTimeImmutable('2026-01-20');

        $this->assertTrue(TaskFactory::make(['due_date' => '2026-01-19'])->isOverdue($today));
        $this->assertFalse(TaskFactory::make(['due_date' => '2026-01-19', 'status' => 'done', 'completed_at' => '2026-01-20 08:00:00'])->isOverdue($today));
    }

    public function testSearchTextCoversTitleNotesAndList(): void
    {
        $task = TaskFactory::make(['title' => 'Pay Rent', 'notes' => 'Before the 5th', 'category' => 'Home']);

        $this->assertSame('pay rent before the 5th home', $task->searchText());
    }

    public function testReadsMysqlDateTimeColumns(): void
    {
        $task = TaskFactory::make(['due_date' => '2026-02-01 00:00:00']);

        $this->assertSame('2026-02-01', $task->dueDate?->toString());
    }
}
