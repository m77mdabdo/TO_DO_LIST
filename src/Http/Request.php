<?php

declare(strict_types=1);

namespace App\Http;

/**
 * An immutable snapshot of the incoming request.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers lower-cased names
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private array $query = [],
        private array $body = [],
        private array $headers = [],
        public readonly string $ip = '0.0.0.0',
        private array $attributes = [],
    ) {
    }

    public static function fromGlobals(string $basePath): self
    {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = substr($uri, strlen(rtrim($basePath, '/'))) ?: '/';
        $path = '/' . trim(preg_replace('#^/index\.php#', '', $path) ?? '/', '/');

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }

        return new self(
            method: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: $path,
            query: $_GET,
            body: $_POST,
            headers: $headers,
            ip: $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        );
    }

    /** Trimmed text from the body or query string; arrays become the default. */
    public function input(string $key, string $default = ''): string
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    /** Raw string value without trimming, e.g. for passwords. */
    public function raw(string $key): string
    {
        $value = $this->body[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param list<string> $keys
     * @return array<string, string>
     */
    public function only(array $keys): array
    {
        return array_combine($keys, array_map(fn (string $key) => $this->input($key), $keys));
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function wantsJson(): bool
    {
        return str_contains($this->header('accept') ?? '', 'application/json');
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;

        return $clone;
    }
}
