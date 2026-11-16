<?php

declare(strict_types=1);

namespace App\Security;

/**
 * The request limits of the app. Each module adds the limits of its own
 * routes from its provider (see Identity, Invitation, Integration), and
 * config "rate_limits" can change any of them by name:
 *
 *   'rate_limits' => ['login' => ['max' => 20, 'seconds' => 60]]
 */
final class RateLimits
{
    /** @var array<string, RateLimitRule> */
    private array $rules = [];

    /** @param array<string, array{max?: int, seconds?: int}> $overrides */
    public function __construct(private array $overrides = [])
    {
    }

    public function add(RateLimitRule $rule): void
    {
        $override = $this->overrides[$rule->name] ?? null;

        if (is_array($override)) {
            $rule = $rule->withLimit((int) ($override['max'] ?? $rule->max), (int) ($override['seconds'] ?? $rule->seconds));
        }

        $this->rules[$rule->name] = $rule;
    }

    /** @return list<RateLimitRule> */
    public function matching(string $method, string $path): array
    {
        return array_values(array_filter($this->rules, static fn (RateLimitRule $r) => $r->matches($method, $path)));
    }

    /** @return array<string, RateLimitRule> */
    public function all(): array
    {
        return $this->rules;
    }
}
