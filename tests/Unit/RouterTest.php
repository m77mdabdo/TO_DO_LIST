<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Router;
use Tests\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    public function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/', ['Board', 'index']);
        $this->router->group(['Auth'], function (Router $r) {
            $r->get('/tasks/{id}/edit', ['Tasks', 'edit']);
            $r->post('/tasks/{id}', ['Tasks', 'update']);
        });
    }

    public function testMatchesStaticAndParameterRoutes(): void
    {
        [$route] = $this->router->match(new Request('GET', '/'));
        $this->assertSame(['Board', 'index'], $route->action);

        [$route, $params] = $this->router->match(new Request('GET', '/tasks/42/edit/'));
        $this->assertSame(['Tasks', 'edit'], $route->action);
        $this->assertSame(['id' => 42], $params);
        $this->assertSame(['Auth'], $route->middleware);
    }

    public function testUnknownPathsAre404AndWrongMethodsAre405(): void
    {
        $this->assertSame(404, $this->statusFor(new Request('GET', '/nope')));
        $this->assertSame(404, $this->statusFor(new Request('GET', '/tasks/abc/edit')));
        $this->assertSame(405, $this->statusFor(new Request('GET', '/tasks/3')));
    }

    private function statusFor(Request $request): int
    {
        try {
            $this->router->match($request);
        } catch (HttpException $e) {
            return $e->status;
        }

        return 200;
    }
}
