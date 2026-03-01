<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Task\Board;
use App\Task\BoardStats;
use App\Task\Status;
use DateTimeImmutable;
use Tests\Support\TaskFactory;
use Tests\TestCase;

final class BoardTest extends TestCase
{
    public function testGroupsTasksAndSortsByPriorityThenDueDate(): void
    {
        $board = new Board([
            $low   = TaskFactory::make(['priority' => 'low']),
            $later = TaskFactory::make(['due_date' => '2026-02-01']),
            $soon  = TaskFactory::make(['due_date' => '2026-01-15']),
            $high  = TaskFactory::make(['priority' => 'high']),
            TaskFactory::make(['status' => 'doing']),
        ]);

        $this->assertSame([$high, $soon, $later, $low], $board->column(Status::Todo));
        $this->assertCount(1, $board->column(Status::Doing));
        $this->assertSame(5, $board->count());
    }

    public function testDoneColumnShowsNewestFirst(): void
    {
        $old = TaskFactory::make(['status' => 'done', 'completed_at' => '2026-01-10 10:00:00']);
        $new = TaskFactory::make(['status' => 'done', 'completed_at' => '2026-01-12 10:00:00']);

        $this->assertSame([$new, $old], (new Board([$old, $new]))->column(Status::Done));
    }

    public function testStats(): void
    {
        $today = new DateTimeImmutable('2026-01-14 12:00');
        $board = new Board([
            TaskFactory::make(['due_date' => '2026-01-14']),
            TaskFactory::make(['due_date' => '2026-01-01']),
            TaskFactory::make(['status' => 'doing']),
            TaskFactory::make(['status' => 'done', 'completed_at' => '2026-01-14 08:00:00', 'due_date' => '2026-01-01']),
        ]);

        $stats = BoardStats::of($board, $today);

        $this->assertSame(3, $stats->open);
        $this->assertSame(1, $stats->overdue);
        $this->assertSame(1, $stats->dueToday);
        $this->assertSame(1, $stats->doneToday);
        $this->assertSame(25, $stats->percent());
    }
}
