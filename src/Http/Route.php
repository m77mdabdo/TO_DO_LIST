<?php

declare(strict_types=1);

namespace App\Http;

final class Route
{
    /** Parameter types: {id} is a positive integer, {board:ulid} a 26-char public id, {t:token} a 43-char secret. */
    private const TYPES = [
        'int'  => '[1-9]\d{0,17}',
        'ulid' => '[0-9A-HJKMNP-TV-Z]{26}',
        'slug' => '[a-z]{2,10}',
        // 32 random bytes in URL-safe base64 (invitation links)
        'token' => '[A-Za-z0-9_-]{43}',
    ];

    private string $regex;

    /** @var array<string, string> parameter name => type */
    private array $types = [];

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
        // "/boards/{board:ulid}/lists/{id}" -> "#^/boards/(?P<board>[0-9A-Z]{26})/lists/(?P<id>\d+)$#"
        $regex = preg_replace_callback('#\{(\w+)(?::(\w+))?\}#', function (array $m): string {
            $type = $m[2] ?? 'int';
            $this->types[$m[1]] = $type;

            return '(?P<' . $m[1] . '>' . (self::TYPES[$type] ?? throw new \InvalidArgumentException("Unknown route parameter type [$type].")) . ')';
        }, self::normalize($pattern));

        $this->regex = '#^' . $regex . '$#';
    }

    public static function normalize(string $path): string
    {
        return rtrim($path, '/') ?: '/';
    }

    public function isStatic(): bool
    {
        return !str_contains($this->pattern, '{');
    }

    /** The first path segment when it's literal ("boards" for /boards/{id}), else "". */
    public function firstSegment(): string
    {
        $segment = explode('/', ltrim($this->pattern, '/'), 2)[0];

        return str_contains($segment, '{') ? '' : $segment;
    }

    /** @return array<string, int|string>|null route parameters when the path matches */
    public function match(string $path): ?array
    {
        if (!preg_match($this->regex, self::normalize($path), $matches)) {
            return null;
        }

        $params = [];

        foreach ($this->types as $name => $type) {
            $params[$name] = $type === 'int' ? (int) $matches[$name] : $matches[$name];
        }

        return $params;
    }
}
