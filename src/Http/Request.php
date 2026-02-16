<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The incoming HTTP request. Built once from the globals, so the rest
 * of the app never reads $_GET, $_POST or $_SERVER directly.
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers lower-cased names
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $body = [],
        private array $headers = [],
        private string $ip = '0.0.0.0',
        private bool $secure = false,
    ) {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
    }

    public static function fromGlobals(string $basePath = ''): self
    {
        $uri  = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = $basePath !== '' && str_starts_with($uri, $basePath) ? substr($uri, strlen($basePath)) : $uri;

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }

        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            path: rawurldecode($path),
            query: $_GET,
            body: $_POST,
            headers: $headers,
            ip: $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            secure: !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** Path inside the app, always starting with "/". */
    public function path(): string
    {
        return $this->path;
    }

    /** A trimmed string from the body (or query string), never an array. */
    public function input(string $key, string $default = ''): string
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    /**
     * Only the string fields from the body, trimmed.
     *
     * @param list<string> $keys
     * @return array<string, string>
     */
    public function only(array $keys): array
    {
        return array_combine($keys, array_map(fn (string $key) => $this->input($key), $keys));
    }

    /** The raw value, for passwords that must not be trimmed. */
    public function raw(string $key): string
    {
        $value = $this->body[$key] ?? '';

        return is_string($value) ? $value : '';
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

    public function wantsJson(): bool
    {
        return str_contains((string) $this->header('Accept'), 'application/json');
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $copy = clone $this;
        $copy->attributes[$key] = $value;

        return $copy;
    }
}
