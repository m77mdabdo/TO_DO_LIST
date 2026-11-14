<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure;

use App\Modules\Integration\Contract\Webhook;
use App\Modules\Integration\Contract\WebhookDelivery;
use App\Modules\Integration\Domain\WebhookRepository;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use PDO;

final class PdoWebhooks implements WebhookRepository
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function create(int $boardId, string $url, string $secret, array $events, int $createdBy): Webhook
    {
        $publicId = Ulid::generate();
        $this->db->prepare('INSERT INTO webhooks (public_id, board_id, url, secret, events, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$publicId, $boardId, $url, $secret, json_encode(array_values($events)), $createdBy, $this->now()]);

        return $this->findByPublicId($publicId) ?? throw new \LogicException('Webhook not saved.');
    }

    public function findByPublicId(string $publicId): ?Webhook
    {
        $stmt = $this->db->prepare('SELECT * FROM webhooks WHERE public_id = ?');
        $stmt->execute([$publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Webhook::fromRow($row) : null;
    }

    public function find(int $id): ?Webhook
    {
        $stmt = $this->db->prepare('SELECT * FROM webhooks WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Webhook::fromRow($row) : null;
    }

    public function forBoard(int $boardId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM webhooks WHERE board_id = ? ORDER BY id');
        $stmt->execute([$boardId]);

        return array_map([Webhook::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $this->db->prepare('UPDATE webhooks SET enabled = ? WHERE id = ?')->execute([$enabled ? 1 : 0, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM webhooks WHERE id = ?')->execute([$id]);
    }

    public function queue(int $webhookId, string $event, string $payload): void
    {
        $this->db->prepare('INSERT INTO webhook_deliveries (webhook_id, event, payload, next_attempt_at, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$webhookId, $event, $payload, $this->now(), $this->now()]);
    }

    public function due(int $limit): array
    {
        $stmt = $this->db->prepare("SELECT * FROM webhook_deliveries WHERE status = 'pending' AND next_attempt_at <= ? ORDER BY next_attempt_at, id LIMIT " . max(1, $limit));
        $stmt->execute([$this->now()]);

        return array_map([WebhookDelivery::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function markSent(int $deliveryId, int $code): void
    {
        $this->db->prepare("UPDATE webhook_deliveries SET status = 'sent', attempts = attempts + 1, response_code = ?, error = '', delivered_at = ? WHERE id = ?")
            ->execute([$code, $this->now(), $deliveryId]);
    }

    public function markFailed(int $deliveryId, ?int $code, string $error, ?string $retryAt): void
    {
        $this->db->prepare('UPDATE webhook_deliveries SET status = ?, attempts = attempts + 1, response_code = ?, error = ?, next_attempt_at = COALESCE(?, next_attempt_at) WHERE id = ?')
            ->execute([$retryAt === null ? 'failed' : 'pending', $code, mb_substr($error, 0, 500), $retryAt, $deliveryId]);
    }

    public function deliveries(int $webhookId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM webhook_deliveries WHERE webhook_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit));
        $stmt->execute([$webhookId]);

        return array_map([WebhookDelivery::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
