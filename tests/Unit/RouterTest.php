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

    public function testUlidParametersStayStrings(): void
    {
        $this->router->get('/boards/{board:ulid}/cards/{id}', ['Cards', 'show']);

        [, $params] = $this->router->match(new Request('GET', '/boards/01JCQ2ZK8S6M3X0V4T9N7B5R2D/cards/7'));

        $this->assertSame(['board' => '01JCQ2ZK8S6M3X0V4T9N7B5R2D', 'id' => 7], $params);
        $this->assertSame(404, $this->statusFor(new Request('GET', '/boards/not-a-ulid/cards/7')));
        $this->assertSame(404, $this->statusFor(new Request('GET', '/boards/01jcq2zk8s6m3x0v4t9n7b5r2d/cards/7')));
    }

    public function testStaticRoutesWinOverDynamicOnes(): void
    {
        $this->router->get('/tasks/export', ['Tasks', 'export']);

        [$route] = $this->router->match(new Request('GET', '/tasks/export'));

        $this->assertSame(['Tasks', 'export'], $route->action);
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
