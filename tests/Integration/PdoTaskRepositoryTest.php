<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Support\FrozenClock;
use App\Task\DueDate;
use App\Task\PdoTaskRepository;
use App\Task\Priority;
use App\Task\Status;
use App\Task\TaskData;
use PDO;
use Tests\Support\Database;
use Tests\TestCase;

final class PdoTaskRepositoryTest extends TestCase
{
    private PDO $pdo;
    private FrozenClock $clock;
    private PdoTaskRepository $tasks;
    private int $sara;
    private int $omar;

    public function setUp(): void
    {
        $this->pdo   = Database::fresh();
        $this->clock = new FrozenClock('2026-01-20 10:00:00');
        $this->tasks = new PdoTaskRepository($this->pdo, $this->clock);
        $this->sara  = Database::user($this->pdo, 'sara@example.com');
        $this->omar  = Database::user($this->pdo, 'omar@example.com');
    }

    public function testCreatesAndReadsATask(): void
    {
        $task = $this->tasks->create($this->sara, new TaskData(
            title: 'Write report',
            priority: Priority::High,
            category: 'Work',
            dueDate: DueDate::fromString('2026-01-22'),
        ));

        $this->assertSame('Write report', $task->title);
        $this->assertSame(Status::Todo, $task->status);
        $this->assertSame(Priority::High, $task->priority);
        $this->assertSame('2026-01-22', $task->dueDate?->toString());
        $this->assertSame(['Work'], $this->tasks->categories($this->sara));
    }

    public function testUsersCannotTouchEachOthersTasks(): void
    {
        $task = $this->tasks->create($this->sara, new TaskData('Private'));

        $this->assertNull($this->tasks->find($this->omar, $task->id));
        $this->assertNull($this->tasks->update($this->omar, $task->id, new TaskData('Hacked')));
        $this->assertFalse($this->tasks->delete($this->omar, $task->id));
        $this->assertSame('Private', $this->tasks->find($this->sara, $task->id)?->title);
    }

    public function testMovingToDoneStampsTheCompletionTime(): void
    {
        $task = $this->tasks->create($this->sara, new TaskData('Ship it'));

        $done = $this->tasks->move($this->sara, $task->id, Status::Done);
        $this->assertSame('2026-01-20 10:00:00', $done?->completedAt?->format('Y-m-d H:i:s'));

        $back = $this->tasks->move($this->sara, $task->id, Status::Doing);
        $this->assertNull($back?->completedAt);
    }

    public function testClearsOnlyFinishedTasks(): void
    {
        $a = $this->tasks->create($this->sara, new TaskData('A'));
        $this->tasks->create($this->sara, new TaskData('B'));
        $this->tasks->move($this->sara, $a->id, Status::Done);

        $this->assertSame(1, $this->tasks->deleteDone($this->sara));
        $this->assertCount(1, $this->tasks->allFor($this->sara));
    }
}
