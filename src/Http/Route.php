<?php

declare(strict_types=1);

namespace App\Http;

final class Route
{
    private string $regex;

    /**
     * @param array{0: class-string, 1: string} $action controller class and method
     * @param list<class-string<Middleware>> $middleware
     */
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly array $action,
        public array $middleware = [],
    ) {
        // "/tasks/{id}/edit" -> "#^/tasks/(?P<id>\d+)/edit$#"
        $this->regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', rtrim($pattern, '/') ?: '/') . '$#';
    }

    /** @return array<string, int>|null route parameters when the path matches */
    public function match(string $path): ?array
    {
        if (!preg_match($this->regex, rtrim($path, '/') ?: '/', $matches)) {
            return null;
        }

        return array_map('intval', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
    }
}
