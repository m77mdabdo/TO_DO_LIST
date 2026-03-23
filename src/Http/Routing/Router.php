<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\HttpException;
use App\Http\Request;

/**
 * Maps "METHOD /path/{param}" to a controller method.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @param array{0: class-string, 1: string} $action */
    public function get(string $path, array $action): Route
    {
        return $this->add('GET', $path, $action);
    }

    /** @param array{0: class-string, 1: string} $action */
    public function post(string $path, array $action): Route
    {
        return $this->add('POST', $path, $action);
    }

    /**
     * Runs $define, then adds the middleware to every route it registered.
     *
     * @param list<class-string> $middleware
     */
    public function group(array $middleware, callable $define): void
    {
        $before = count($this->routes);
        $define($this);

        foreach (array_slice($this->routes, $before) as $route) {
            $route->middleware(...$middleware);
        }
    }

    /**
     * @return array{0: Route, 1: array<string, string>}
     * @throws HttpException 404 for an unknown path, 405 for a known path with the wrong method
     */
    public function match(Request $request): array
    {
        $pathExists = false;
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method(); // HEAD is a GET without the body

        foreach ($this->routes as $route) {
            $params = $route->matchPath($request->path());

            if ($params === null) {
                continue;
            }

            if ($route->method === $method) {
                return [$route, $params];
            }

            $pathExists = true;
        }

        throw $pathExists ? HttpException::methodNotAllowed() : HttpException::notFound();
    }

    /** @param array{0: class-string, 1: string} $action */
    private function add(string $method, string $path, array $action): Route
    {
        return $this->routes[] = new Route($method, '/' . trim($path, '/'), $action);
    }
}
