<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var list<class-string<Middleware>> */
    private array $groupMiddleware = [];

    /** @param array{0: class-string, 1: string} $action */
    public function get(string $pattern, array $action): Route
    {
        return $this->add('GET', $pattern, $action);
    }

    /** @param array{0: class-string, 1: string} $action */
    public function post(string $pattern, array $action): Route
    {
        return $this->add('POST', $pattern, $action);
    }

    /**
     * Routes registered inside the callback get the given middleware.
     *
     * @param list<class-string<Middleware>> $middleware
     */
    public function group(array $middleware, callable $routes): void
    {
        $previous = $this->groupMiddleware;
        $this->groupMiddleware = [...$previous, ...$middleware];
        $routes($this);
        $this->groupMiddleware = $previous;
    }

    /**
     * @return array{0: Route, 1: array<string, int>}
     * @throws HttpException 404 when nothing matches, 405 for the wrong method
     */
    public function match(Request $request): array
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $route->match($request->path);

            if ($params === null) {
                continue;
            }

            if ($route->method === $request->method) {
                return [$route, $params];
            }

            $allowed[] = $route->method;
        }

        if ($allowed) {
            throw new HttpException(405, 'This action only accepts form submissions.');
        }

        throw HttpException::notFound();
    }

    /** @param array{0: class-string, 1: string} $action */
    private function add(string $method, string $pattern, array $action): Route
    {
        return $this->routes[] = new Route($method, $pattern, $action, $this->groupMiddleware);
    }
}
