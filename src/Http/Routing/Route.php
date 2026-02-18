<?php

declare(strict_types=1);

namespace App\Http\Routing;

final class Route
{
    private string $regex;

    /** @var list<class-string> */
    private array $middleware = [];

    /**
     * @param array{0: class-string, 1: string} $action controller class and method
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $action,
    ) {
        $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $path);
        $this->regex = '#^' . $pattern . '$#';
    }

    /** @param class-string ...$middleware */
    public function middleware(string ...$middleware): self
    {
        $this->middleware = [...$this->middleware, ...$middleware];

        return $this;
    }

    /** @return list<class-string> */
    public function middlewareList(): array
    {
        return $this->middleware;
    }

    /** @return array<string, string>|null the path parameters, or null when the path doesn't match */
    public function matchPath(string $path): ?array
    {
        if (!preg_match($this->regex, $path, $matches)) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
