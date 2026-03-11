<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Kernel;
use App\Http\Middleware\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Router;
use App\Support\Container;
use Closure;
use Tests\TestCase;

final class KernelTest extends TestCase
{
    public function testRunsMiddlewareInOrderAroundTheController(): void
    {
        $container = new Container();
        $container->set(FakeController::class, fn () => new FakeController());
        $container->set('outer', fn () => new TagMiddleware('outer'));
        $container->set('inner', fn () => new TagMiddleware('inner'));

        $router = new Router();
        $router->get('/hello/{name}', [FakeController::class, 'hello'])->middleware('inner');

        $response = (new Kernel($container, $router, ['outer']))->handle(new Request('GET', '/hello/Sara'));

        $this->assertSame('outer>inner>Hello Sara<inner<outer', $response->body());
    }
}

final class FakeController
{
    public function hello(Request $request): Response
    {
        return Response::html($request->attribute('trail') . 'Hello ' . $request->attribute('name'));
    }
}

final class TagMiddleware implements Middleware
{
    public function __construct(private string $tag)
    {
    }

    public function process(Request $request, Closure $next): Response
    {
        $response = $next($request->withAttribute('trail', $request->attribute('trail') . "{$this->tag}>"));

        return Response::html($response->body() . "<{$this->tag}");
    }
}
