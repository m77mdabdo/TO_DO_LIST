<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Routing\Router;
use Tests\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    public function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/', ['Board', 'index']);
        $this->router->post('/tasks/{id}/move', ['Tasks', 'move']);
    }

    public function testMatchesAndExtractsParameters(): void
    {
        [$route, $params] = $this->router->match(new Request('POST', '/tasks/12/move'));

        $this->assertSame(['Tasks', 'move'], $route->action);
        $this->assertSame(['id' => '12'], $params);
    }

    public function testUnknownPathIsNotFound(): void
    {
        $this->assertStatus(404, new Request('GET', '/nope'));
        $this->assertStatus(404, new Request('POST', '/tasks/12/move/extra'));
    }

    public function testWrongMethodIsNotAllowed(): void
    {
        $this->assertStatus(405, new Request('GET', '/tasks/12/move'));
    }

    public function testGroupsAddMiddleware(): void
    {
        $this->router->group(['Auth'], function (Router $router): void {
            $router->get('/settings', ['Settings', 'show'])->middleware('Csrf');
        });

        [$route] = $this->router->match(new Request('GET', '/settings'));
        [$home]  = $this->router->match(new Request('GET', '/'));

        $this->assertSame(['Csrf', 'Auth'], $route->middlewareList());
        $this->assertSame([], $home->middlewareList());
    }

    private function assertStatus(int $status, Request $request): void
    {
        try {
            $this->router->match($request);
        } catch (HttpException $e) {
            $this->assertSame($status, $e->status);
            return;
        }

        $this->assertTrue(false, "Expected HTTP $status.");
    }
}
