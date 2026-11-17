<?php

declare(strict_types=1);

namespace App\Modules\Integration\Contract;

/**
 * One kind of ready-made integration (Slack, ...). A new kind is a class
 * implementing this, added to IntegrationTypes; the center, the encryption,
 * the queue, the retries and the change log are shared.
 */
interface IntegrationType
{
    /** Stable key saved with each integration, e.g. "slack". */
    public function key(): string;

    public function name(): string;

    /** One sentence for the center. */
    public function description(): string;

    /** What it can see and do, in plain words, shown before connecting. @return list<string> */
    public function permissions(): array;

    /** The label of the one secret it needs (e.g. "Slack webhook address"). */
    public function secretLabel(): string;

    /** How to get that secret, step by step. @return list<string> */
    public function setupSteps(): array;

    /** An error message when the secret is not acceptable, else null. */
    public function checkSecret(string $secret): ?string;

    /** A few characters that identify the secret without revealing it. */
    public function hint(string $secret): string;

    /** Events it can send: key => description. @return array<string, string> */
    public function events(): array;

    /**
     * The HTTP request for one message: where to, and the JSON body.
     *
     * @param array<string, mixed> $payload as built by Webhooks::payload, plus "kind" (the event key)
     * @return array{url: string, body: string}
     */
    public function request(string $secret, array $payload): array;

    /** A short test message's request. @return array{url: string, body: string} */
    public function testRequest(string $secret, string $workspaceName): array;
}
