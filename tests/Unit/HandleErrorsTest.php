<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\HttpException;
use App\Http\Middleware\HandleErrors;
use App\Http\Request;
use App\Http\Response;
use App\Task\TaskNotFound;
use App\View\View;
use RuntimeException;
use Tests\TestCase;

final class HandleErrorsTest extends TestCase
{
    private HandleErrors $middleware;

    public function setUp(): void
    {
        $view = new View(dirname(__DIR__) . '/Fixtures/views');
        $view->share('content', '');
        $this->middleware = new HandleErrors($view);
    }

    public function testHttpErrorsKeepTheirStatusAndMessage(): void
    {
        $response = $this->json(fn () => throw HttpException::tokenMismatch());

        $this->assertSame(419, $response->status());
        $this->assertSame('{"ok":false,"message":"Your session expired. Refresh the page and try again."}', $response->body());
    }

    public function testDomainNotFoundBecomesA404(): void
    {
        $this->assertSame(404, $this->json(fn () => throw new TaskNotFound())->status());
    }

    public function testUnexpectedErrorsHideTheirDetails(): void
    {
        $log = ini_set('error_log', '/dev/null');
        $response = $this->json(fn () => throw new RuntimeException('SQLSTATE secret'));
        ini_set('error_log', (string) $log);

        $this->assertSame(500, $response->status());
        $this->assertFalse(str_contains($response->body(), 'secret'));
    }

    private function json(callable $controller): Response
    {
        return $this->middleware->process(new Request('GET', '/', headers: ['accept' => 'application/json']), $controller(...));
    }
}
