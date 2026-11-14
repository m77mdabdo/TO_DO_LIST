<?php

declare(strict_types=1);

namespace App\Modules\Integration\Domain;

/** Sends one HTTP POST. Connects to $ip (already checked) so DNS can't be switched under us. */
interface WebhookTransport
{
    /**
     * @param array<string, string> $headers
     * @return array{status: ?int, error: string}
     */
    public function post(string $url, string $ip, array $headers, string $body): array;
}
