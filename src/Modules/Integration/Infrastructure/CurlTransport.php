<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure;

use App\Modules\Integration\Domain\WebhookTransport;

/** Posts with cURL to the checked address only: no redirects, https only, short timeouts. */
final class CurlTransport implements WebhookTransport
{
    public function post(string $url, string $ip, array $headers, string $body): array
    {
        if (!function_exists('curl_init')) {
            return ['status' => null, 'error' => 'The PHP curl extension is not installed.'];
        }

        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $port = (int) ($parts['port'] ?? 443);
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            // Pin the host to the address the guard approved (no DNS rebinding).
            CURLOPT_RESOLVE        => [$host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
        ]);
        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = $ok === false ? curl_error($curl) : '';
        curl_close($curl);

        return ['status' => $status > 0 ? $status : null, 'error' => $error];
    }
}
