<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class SecurityTest extends TestCase
{
    public function testGuestsAreSentToLogin(): void
    {
        $response = (new App())->get('/');

        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testPostsWithoutTheCsrfTokenAreRejected(): void
    {
        $f = new BoardFixture(new App());

        $response = $f->app->post('/boards/' . $f->board->publicId . '/lists', ['name' => 'Sneaky', '_token' => 'forged']);

        $this->assertSame(419, $response->status);
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
    }

    public function testResponsesCarrySecurityHeaders(): void
    {
        $response = (new App())->get('/login');

        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertTrue(str_contains((string) $response->header('Content-Security-Policy'), "script-src 'self'"));
    }

    public function testSequentialIdsAreNeverAcceptedInUrls(): void
    {
        $f = new BoardFixture(new App());

        $this->assertSame(404, $f->app->get('/boards/' . $f->board->id)->status);
    }

    public function testUserTextIsEscaped(): void
    {
        $f = new BoardFixture(new App());
        $f->app->post('/boards/' . $f->board->publicId . '/cards', ['list_id' => (string) $f->todo->id, 'title' => '<script>alert(1)</script>']);

        $html = $f->app->page('/boards/' . $f->board->publicId);

        $this->assertFalse(str_contains($html, '<script>alert(1)'));
        $this->assertTrue(str_contains($html, '&lt;script&gt;alert(1)'));
    }
}
