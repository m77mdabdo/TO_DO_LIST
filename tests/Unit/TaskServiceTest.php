<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\FrozenClock;
use App\Task\Priority;
use App\Task\Status;
use App\Task\TaskNotFound;
use App\Task\TaskService;
use App\Validation\ValidationException;
use App\Validation\Validator;
use Tests\Support\InMemoryTaskRepository;
use Tests\TestCase;

final class TaskServiceTest extends TestCase
{
    private TaskService $service;

    public function setUp(): void
    {
        $clock = new FrozenClock('2026-01-20 09:00:00');
        $this->service = new TaskService(new InMemoryTaskRepository($clock), new Validator(), $clock);
    }

    public function testCreatesATaskWithCleanedInput(): void
    {
        $task = $this->service->create(1, ['title' => '  Buy milk ', 'notes' => '', 'priority' => '', 'category' => 'Home']);

        $this->assertSame('Buy milk', $task->title);
        $this->assertNull($task->notes);
        $this->assertSame(Priority::Normal, $task->priority);
        $this->assertSame(['Home'], $this->service->categories(1));
    }

    public function testRejectsInvalidInput(): void
    {
        try {
            $this->service->create(1, ['title' => 'x', 'priority' => 'urgent', 'due_date' => '2026-13-01']);
        } catch (ValidationException $e) {
            $this->assertSame(['title', 'priority', 'due_date'], array_keys($e->errors()));
            return;
        }

        $this->assertTrue(false, 'Expected a ValidationException.');
    }

    public function testMovesTasksAndCountsThem(): void
    {
        $task = $this->service->create(1, ['title' => 'Report']);
        $this->service->move(1, $task->id, Status::Done);

        $stats = $this->service->stats(1);

        $this->assertSame(1, $stats->done);
        $this->assertSame(1, $stats->doneToday);
        $this->assertSame(100, $stats->percent());
    }

    public function testAnotherUsersTaskIsNotFound(): void
    {
        $task = $this->service->create(1, ['title' => 'Mine']);

        $this->assertThrows(TaskNotFound::class, fn () => $this->service->update(2, $task->id, ['title' => 'Yours']));
        $this->assertThrows(TaskNotFound::class, fn () => $this->service->delete(2, $task->id));
        $this->assertThrows(TaskNotFound::class, fn () => $this->service->move(2, $task->id, Status::Done));
    }
}
