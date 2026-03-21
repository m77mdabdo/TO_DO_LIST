<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\TestApp;
use Tests\TestCase;

final class AuthFlowTest extends TestCase
{
    private TestApp $app;

    public function setUp(): void
    {
        $this->app = new TestApp();
    }

    public function testGuestsAreSentToTheLoginPage(): void
    {
        $this->assertSame('/login', $this->app->get('/')->header('Location'));
        $this->assertSame(200, $this->app->get('/login')->status());
    }

    public function testSignUpLogsTheUserIn(): void
    {
        $this->app->signUp('Sara Ali');

        $board = $this->app->get('/');

        $this->assertSame(200, $board->status());
        $this->assertTrue(str_contains($board->body(), 'Good morning, <span class="gradient-text" dir="auto">Sara</span>'));
        $this->assertSame('/', $this->app->get('/login')->header('Location'));
    }

    public function testFailedLoginReturnsToTheFormWithTheEmail(): void
    {
        $this->app->signUp();
        $this->app->post('/logout');

        $response = $this->app->post('/login', ['email' => 'sara@example.com', 'password' => 'wrong-one']);
        $page = $this->app->get('/login')->body();

        $this->assertSame('/login', $response->header('Location'));
        $this->assertTrue(str_contains($page, 'That email and password don&#039;t match.'));
        $this->assertTrue(str_contains($page, 'value="sara@example.com"'));
    }

    public function testLoginAndLogout(): void
    {
        $this->app->signUp();
        $this->app->post('/logout');

        $this->assertSame('/login', $this->app->get('/')->header('Location'));

        $this->app->post('/login', ['email' => 'SARA@example.com', 'password' => 'secret123']);

        $this->assertSame(200, $this->app->get('/')->status());
    }

    public function testFormsWithoutTheTokenAreRejected(): void
    {
        $response = $this->app->post('/login', ['_token' => 'forged', 'email' => 'a@b.co', 'password' => 'x']);

        $this->assertSame(419, $response->status());
    }

    public function testPagesSendSecurityHeaders(): void
    {
        $this->assertSame('DENY', $this->app->get('/login')->header('X-Frame-Options'));
        $this->assertSame('DENY', $this->app->get('/missing')->header('X-Frame-Options'));
    }
}
