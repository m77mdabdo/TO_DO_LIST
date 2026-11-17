<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure;

use App\Modules\Notification\Contract\Notification;
use App\Modules\Notification\Domain\NotificationStore;
use App\Support\Clock;
use PDO;
use PDOException;

final class PdoNotifications implements NotificationStore
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function watch(int $userId, string $target, int $targetId, int $boardId): void
    {
        if (!$this->isWatching($userId, $target, $targetId)) {
            $this->db->prepare('INSERT INTO watches (user_id, target, target_id, board_id, created_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$userId, $target, $targetId, $boardId, $this->now()]);
        }
    }

    public function unwatch(int $userId, string $target, int $targetId): void
    {
        $this->db->prepare('DELETE FROM watches WHERE user_id = ? AND target = ? AND target_id = ?')->execute([$userId, $target, $targetId]);
    }

    public function isWatching(int $userId, string $target, int $targetId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM watches WHERE user_id = ? AND target = ? AND target_id = ?');
        $stmt->execute([$userId, $target, $targetId]);

        return $stmt->fetchColumn() !== false;
    }

    public function watchersOfCard(int $cardId, int $boardId): array
    {
        $stmt = $this->db->prepare("SELECT user_id FROM watches WHERE target = 'card' AND target_id = ?
                                    UNION SELECT user_id FROM watches WHERE target = 'board' AND target_id = ?");
        $stmt->execute([$cardId, $boardId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function add(int $userId, string $type, ?int $actorId, ?int $boardId, ?int $cardId, string $subject, array $data, string $link, string $dedupeKey): bool
    {
        try {
            $this->db->prepare('INSERT INTO notifications (user_id, type, actor_id, board_id, card_id, subject, data, link, dedupe_key, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$userId, $type, $actorId, $boardId, $cardId, mb_substr($subject, 0, 250), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $link, mb_substr($dedupeKey, 0, 120), $this->now()]);
        } catch (PDOException $e) {
            // 23000: the (user, key) pair exists, so this event was already announced to them.
            if ((string) $e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }

        return true;
    }

    public function latest(int $userId, ?int $beforeId, int $limit): array
    {
        $sql = 'SELECT id, type, actor_id, subject, data, link, created_at, read_at FROM notifications WHERE user_id = ?'
            . ($beforeId !== null ? ' AND id < ?' : '') . ' ORDER BY id DESC LIMIT ' . max(1, $limit);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($beforeId !== null ? [$userId, $beforeId] : [$userId]);

        return array_map([$this, 'fromRow'], $stmt->fetchAll());
    }

    public function unreadCount(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function find(int $id, int $userId): ?Notification
    {
        $stmt = $this->db->prepare('SELECT id, type, actor_id, subject, data, link, created_at, read_at FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row ? $this->fromRow($row) : null;
    }

    public function markRead(int $id, int $userId): void
    {
        $this->db->prepare('UPDATE notifications SET read_at = ? WHERE id = ? AND user_id = ? AND read_at IS NULL')->execute([$this->now(), $id, $userId]);
    }

    public function markAllRead(int $userId): void
    {
        $this->db->prepare('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL')->execute([$this->now(), $userId]);
    }

    public function emailEnabled(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT email_enabled FROM notification_settings WHERE user_id = ?');
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();

        return $value === false || (int) $value === 1;
    }

    public function setEmailEnabled(int $userId, bool $enabled): void
    {
        $current = $this->preferences($userId);
        $this->savePreferences($userId, $enabled, $current['muted'], $current['emailOff']);
    }

    public function preferences(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT email_enabled, muted_types, email_off_types FROM notification_settings WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return ['email' => true, 'muted' => [], 'emailOff' => []];
        }
        $list = static function (mixed $json): array {
            $value = json_decode((string) $json, true);

            return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
        };

        return ['email' => (int) $row['email_enabled'] === 1, 'muted' => $list($row['muted_types']), 'emailOff' => $list($row['email_off_types'])];
    }

    public function savePreferences(int $userId, bool $email, array $muted, array $emailOff): void
    {
        $this->db->prepare('DELETE FROM notification_settings WHERE user_id = ?')->execute([$userId]);
        $this->db->prepare('INSERT INTO notification_settings (user_id, email_enabled, muted_types, email_off_types) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $email ? 1 : 0, json_encode(array_values($muted), JSON_THROW_ON_ERROR), json_encode(array_values($emailOff), JSON_THROW_ON_ERROR)]);
    }

    public function prune(int $days): int
    {
        $stmt = $this->db->prepare('DELETE FROM notifications WHERE created_at < ?');
        $stmt->execute([$this->clock->now()->modify('-' . max(1, $days) . ' days')->format('Y-m-d H:i:s')]);

        return $stmt->rowCount();
    }

    /** @param array<string, mixed> $row */
    private function fromRow(array $row): Notification
    {
        $data = json_decode((string) $row['data'], true);

        return new Notification(
            (int) $row['id'],
            (string) $row['type'],
            $row['actor_id'] !== null ? (int) $row['actor_id'] : null,
            (string) $row['subject'],
            is_array($data) ? array_map('strval', $data) : [],
            (string) $row['link'],
            (string) $row['created_at'],
            $row['read_at'] !== null ? (string) $row['read_at'] : null,
        );
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
