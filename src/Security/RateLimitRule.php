<?php

declare(strict_types=1);

namespace App\Security;

/**
 * "At most $max requests per $seconds" for the requests that match
 * $method and $path. $path is a route path where * stands for one
 * segment and a trailing /** for anything below.
 *
 * $by says whose requests are counted together:
 *  - "ip"          the client address
 *  - "user"        the signed-in user (the IP when signed out)
 *  - "token"       the API bearer token (the IP when there is none)
 *  - "field:NAME"  a form field, lower-cased (e.g. field:email); add a second "ip" rule
 *                  for the same path, or changing the field would get around the limit
 */
final class RateLimitRule
{
    private readonly string $regex;

    public function __construct(
        public readonly string $name,
        public readonly string $method,
        public readonly string $path,
        public readonly int $max,
        public readonly int $seconds,
        public readonly string $by = 'ip',
    ) {
        $regex = preg_quote(rtrim($path, '/'), '#');
        $regex = str_replace(['/\*\*', '\*'], ['(?:/.*)?', '[^/]+'], $regex);
        $this->regex = '#^' . $regex . '/?$#';
    }

    public function matches(string $method, string $path): bool
    {
        return ($this->method === '*' || $this->method === $method) && preg_match($this->regex, $path) === 1;
    }

    public function withLimit(int $max, int $seconds): self
    {
        return new self($this->name, $this->method, $this->path, $max, $seconds, $this->by);
    }
}
