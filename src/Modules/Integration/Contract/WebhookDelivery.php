<?php

declare(strict_types=1);

namespace App\Modules\Integration\Contract;

/** One message to a webhook. status: pending | sent | failed (gave up after the retries). */
final class WebhookDelivery
{
    public function __construct(
        public readonly int $id,
        public readonly int $webhookId,
        public readonly string $event,
        public readonly string $payload,
        public readonly string $status,
        public readonly int $attempts,
        public readonly string $nextAttemptAt,
        public readonly ?int $responseCode,
        public readonly string $error,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['webhook_id'],
            (string) $row['event'],
            (string) $row['payload'],
            (string) $row['status'],
            (int) $row['attempts'],
            (string) $row['next_attempt_at'],
            $row['response_code'] !== null ? (int) $row['response_code'] : null,
            (string) $row['error'],
            (string) $row['created_at'],
        );
    }
}
