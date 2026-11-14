<?php

declare(strict_types=1);

namespace App\Modules\Integration\Domain;

use App\Modules\Integration\Contract\Webhook;
use App\Modules\Integration\Contract\WebhookDelivery;

interface WebhookRepository
{
    /** @param list<string> $events */
    public function create(int $boardId, string $url, string $secret, array $events, int $createdBy): Webhook;

    public function findByPublicId(string $publicId): ?Webhook;

    public function find(int $id): ?Webhook;

    /** @return list<Webhook> */
    public function forBoard(int $boardId): array;

    public function setEnabled(int $id, bool $enabled): void;

    public function delete(int $id): void;

    public function queue(int $webhookId, string $event, string $payload): void;

    /** @return list<WebhookDelivery> pending deliveries that are due, oldest first */
    public function due(int $limit): array;

    public function markSent(int $deliveryId, int $code): void;

    /** Records a failed attempt; $retryAt null means give up. */
    public function markFailed(int $deliveryId, ?int $code, string $error, ?string $retryAt): void;

    /** @return list<WebhookDelivery> newest first */
    public function deliveries(int $webhookId, int $limit = 20): array;
}
