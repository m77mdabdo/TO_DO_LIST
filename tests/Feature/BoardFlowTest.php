<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\App;
use Tests\TestCase;

final class BoardFlowTest extends TestCase
{
    private App $app;

    public function setUp(): void
    {
        $this->app = new App();
    }

    public function testGuestsAreSentToLogin(): void
    {
        $response = $this->app->get('/');

        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testSignUpAddMoveAndDeleteATask(): void
    {
        $this->signUp();

        $this->assertSame('/', $this->app->post('/tasks', ['title' => 'Write report', 'category' => 'Work'])->header('Location'));

        $board = $this->app->get('/');
        $this->assertSame(200, $board->status);
        $this->assertTrue(str_contains($board->body, 'Write report'));
        $this->assertTrue(str_contains($board->body, 'Good morning, <span class="gradient-text" dir="auto">Sara</span>'));

        $id = (int) $this->app->pdo()->query('SELECT id FROM tasks')->fetchColumn();

        $moved = $this->app->post("/tasks/$id/move", ['status' => 'done'], json: true);
        $json = json_decode($moved->body, true);
        $this->assertTrue($json['ok']);
        $this->assertSame(100, $json['stats']['percent']);
        $this->assertTrue(str_contains($json['html'], 'is-done'));

        $this->app->post("/tasks/$id/delete");
        $this->assertSame(0, (int) $this->app->pdo()->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
    }

    public function testInvalidTaskGoesBackWithErrors(): void
    {
        $this->signUp();

        $response = $this->app->post('/tasks', ['title' => '', 'notes' => 'keep me']);

        $this->assertSame('/', $response->header('Location'));
        $this->assertTrue(str_contains($this->app->get('/')->body, 'Title is required.'));
    }

    public function testPostsWithoutTheCsrfTokenAreRejected(): void
    {
        $this->signUp();

        $response = $this->app->post('/tasks', ['title' => 'Sneaky', '_token' => 'forged']);

        $this->assertSame(419, $response->status);
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
    }

    public function testEditingSomeoneElsesTaskIs404(): void
    {
        $this->signUp();
        $this->app->post('/tasks', ['title' => 'Mine']);
        $this->app->post('/logout');
        $this->signUp('omar@example.com');

        $this->assertSame(404, $this->app->get('/tasks/1/edit')->status);
        $this->assertSame(404, $this->app->post('/tasks/1/delete')->status);
    }

    public function testExportsTasksAsCsv(): void
    {
        $this->signUp();
        $this->app->post('/tasks', ['title' => 'Pay rent', 'category' => 'Home']);

        $response = $this->app->get('/tasks/export');

        $this->assertSame(200, $response->status);
        $this->assertTrue(str_starts_with((string) $response->header('Content-Type'), 'text/csv'));
        $this->assertTrue(str_contains($response->body, '"Pay rent"'));
    }

    public function testResponsesCarrySecurityHeaders(): void
    {
        $this->assertSame('DENY', $this->app->get('/login')->header('X-Frame-Options'));
    }

    private function signUp(string $email = 'sara@example.com'): void
    {
        $response = $this->app->post('/register', [
            'name' => 'Sara Ali',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $this->assertSame('/', $response->header('Location'));
    }
}
