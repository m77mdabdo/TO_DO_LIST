<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Finds the route for a request without scanning every route:
 *
 *  - static paths ("/boards") sit in a hash map keyed by path: O(1);
 *  - dynamic paths ("/boards/{board:ulid}") are bucketed by their first
 *    literal segment, so only the few routes under "/boards" are tried.
 */
final class Router
{
    /** @var array<string, array<string, Route>> path => method => route */
    private array $static = [];

    /** @var array<string, list<Route>> first segment => routes */
    private array $dynamic = [];

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
     * @return array{0: Route, 1: array<string, int|string>}
     * @throws HttpException 404 when nothing matches, 405 for the wrong method
     */
    public function match(Request $request): array
    {
        $path = Route::normalize($request->path);
        $allowed = false;

        if (isset($this->static[$path])) {
            if (isset($this->static[$path][$request->method])) {
                return [$this->static[$path][$request->method], []];
            }

            $allowed = true;
        }

        $segment = explode('/', ltrim($path, '/'), 2)[0];

        foreach ([...($this->dynamic[$segment] ?? []), ...($this->dynamic[''] ?? [])] as $route) {
            $params = $route->match($path);

            if ($params === null) {
                continue;
            }

            if ($route->method === $request->method) {
                return [$route, $params];
            }

            $allowed = true;
        }

        if ($allowed) {
            throw new HttpException(405, 'This action only accepts form submissions.');
        }

        throw HttpException::notFound();
    }

    /** @param array{0: class-string, 1: string} $action */
    private function add(string $method, string $pattern, array $action): Route
    {
        $route = new Route($method, $pattern, $action, $this->groupMiddleware);

        if ($route->isStatic()) {
            $this->static[Route::normalize($pattern)][$method] = $route;
        } else {
            $this->dynamic[$route->firstSegment()][] = $route;
        }

        return $route;
    }
}
