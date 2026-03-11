<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\Middleware;
use App\Http\Routing\Route;
use App\Http\Routing\Router;
use App\Support\Container;
use Closure;

/**
 * Turns a Request into a Response:
 * global middleware → route match → route middleware → controller.
 */
final class Kernel
{
    /**
     * @param list<class-string<Middleware>> $middleware runs for every request, outermost first
     */
    public function __construct(
        private Container $container,
        private Router $router,
        private array $middleware = [],
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->pipeline($this->middleware, $this->dispatchToRoute(...))($request);
    }

    private function dispatchToRoute(Request $request): Response
    {
        [$route, $params] = $this->router->match($request);

        foreach ($params as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        return $this->pipeline($route->middlewareList(), fn (Request $r) => $this->callController($route, $r))($request);
    }

    private function callController(Route $route, Request $request): Response
    {
        [$class, $method] = $route->action;

        return $this->container->get($class)->$method($request);
    }

    /**
     * @param list<class-string<Middleware>> $middleware
     * @param Closure(Request): Response     $core
     * @return Closure(Request): Response
     */
    private function pipeline(array $middleware, Closure $core): Closure
    {
        return array_reduce(
            array_reverse($middleware),
            fn (Closure $next, string $class) => fn (Request $request): Response
                => $this->container->get($class)->process($request, $next),
            $core,
        );
    }
}
