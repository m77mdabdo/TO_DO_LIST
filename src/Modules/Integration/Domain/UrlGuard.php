<?php

declare(strict_types=1);

namespace App\Modules\Integration\Domain;

use Closure;

/**
 * Webhook addresses are typed by people, so they must not let anyone reach
 * the server's own network (SSRF): only https, no user:password, and the
 * host must resolve to a public address. The caller connects to the address
 * checked here, not to a second lookup.
 */
final class UrlGuard
{
    /** @param Closure(string): list<string> $resolve host => IP addresses */
    public function __construct(private Closure $resolve, private bool $allowPrivate = false)
    {
    }

    public static function system(bool $allowPrivate = false): self
    {
        return new self(static fn (string $host): array => gethostbynamel($host) ?: [], $allowPrivate);
    }

    /** @return array{ok: bool, ip: string, error: string} */
    public function check(string $url): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strlen($url) > 2000 || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return ['ok' => false, 'ip' => '', 'error' => 'Use an https:// address without a user name or password.'];
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolve)($host);
        if ($ips === []) {
            return ['ok' => false, 'ip' => '', 'error' => 'That address could not be found.'];
        }

        foreach ($ips as $ip) {
            if (!$this->allowPrivate && !self::isPublic($ip)) {
                return ['ok' => false, 'ip' => '', 'error' => 'That address points into a private network.'];
            }
        }

        return ['ok' => true, 'ip' => $ips[0], 'error' => ''];
    }

    public static function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // Shared address space (100.64.0.0/10) is not covered by the PHP flags.
        $long = ip2long($ip);

        return $long === false || ($long & 0xFFC00000) !== (ip2long('100.64.0.0') & 0xFFC00000);
    }
}
