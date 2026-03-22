<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\Authenticator;
use App\Task\Status;
use App\Task\Task;
use App\Task\TaskRepository;
use Tests\Support\TestApp;
use Tests\TestCase;

final class TaskFlowTest extends TestCase
{
    private TestApp $app;

    public function setUp(): void
    {
        $this->app = new TestApp();
        $this->app->signUp();
    }

    public function testAddsATaskToTheBoard(): void
    {
        $response = $this->app->post('/tasks', ['title' => 'Write report', 'priority' => 'high', 'category' => 'Work']);
        $board = $this->app->get('/')->body();

        $this->assertSame('/', $response->header('Location'));
        $this->assertTrue(str_contains($board, 'Task added.'));
        $this->assertTrue(str_contains($board, 'Write report'));
        $this->assertTrue(str_contains($board, 'data-filter="cat:work"'));
    }

    public function testInvalidTasksGoBackWithTheError(): void
    {
        $this->app->post('/tasks', ['title' => '1', 'notes' => 'keep me']);
        $board = $this->app->get('/')->body();

        $this->assertTrue(str_contains($board, 'Title must be text, not just a number.'));
        $this->assertTrue(str_contains($board, 'keep me</textarea>'));
        $this->assertCount(0, $this->tasks());
    }

    public function testEditsATask(): void
    {
        $id = $this->addTask('Draft');

        $this->assertSame(200, $this->app->get("/tasks/$id/edit")->status());

        $this->app->post("/tasks/$id", ['title' => 'Final', 'priority' => 'low', 'due_date' => '2026-02-12']);
        $task = $this->tasks()[0];

        $this->assertSame('Final', $task->title);
        $this->assertSame('2026-02-12', $task->dueDate?->toString());
    }

    public function testMovesACardWithFetch(): void
    {
        $id = $this->addTask('Ship it');

        $response = $this->app->post("/tasks/$id/move", ['status' => 'done'], json: true);
        $json = json_decode($response->body(), true);

        $this->assertSame(true, $json['ok']);
        $this->assertTrue(str_contains($json['html'], 'is-done'));
        $this->assertSame(100, $json['stats']['percent']);
        $this->assertSame(Status::Done, $this->tasks()[0]->status);
    }

    public function testRejectsUnknownStatuses(): void
    {
        $id = $this->addTask('Ship it');

        $this->assertSame(422, $this->app->post("/tasks/$id/move", ['status' => 'archived'], json: true)->status());
    }

    public function testDeletesAndClearsTasks(): void
    {
        $first = $this->addTask('One');
        $second = $this->addTask('Two');
        $this->addTask('Three');

        $this->app->post("/tasks/$first/delete");
        $this->app->post("/tasks/$second/move", ['status' => 'done']);
        $this->app->post('/board/clear-done');

        $this->assertSame(['Three'], array_map(fn ($t) => $t->title, $this->tasks()));
    }

    public function testCannotSeeOrChangeSomeoneElsesTask(): void
    {
        $id = $this->addTask('Private');
        $this->app->post('/logout');
        $this->app->signUp('Omar', 'omar@example.com');

        $this->assertSame(404, $this->app->get("/tasks/$id/edit")->status());
        $this->assertSame(404, $this->app->post("/tasks/$id/delete", json: true)->status());
        $this->assertSame(404, $this->app->get('/tasks/abc/edit')->status());
    }

    private function addTask(string $title): int
    {
        $this->app->post('/tasks', ['title' => $title]);
        $this->app->session->pull('success');

        return max(array_map(fn ($t) => $t->id, $this->tasks()));
    }

    /** @return list<Task> */
    private function tasks(): array
    {
        $user = $this->app->container->get(Authenticator::class)->user();

        return $this->app->container->get(TaskRepository::class)->allFor($user->id);
    }
}
