<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain;

use App\Modules\Notification\Contract\Notification;

interface NotificationStore
{
    public function watch(int $userId, string $target, int $targetId, int $boardId): void;

    public function unwatch(int $userId, string $target, int $targetId): void;

    public function isWatching(int $userId, string $target, int $targetId): bool;

    /** @return list<int> people watching the card or its board */
    public function watchersOfCard(int $cardId, int $boardId): array;

    /**
     * Saves a notification unless this user already has one with the same
     * key. Returns false for such a duplicate.
     *
     * @param array<string, string> $data
     */
    public function add(int $userId, string $type, ?int $actorId, ?int $boardId, ?int $cardId, string $subject, array $data, string $link, string $dedupeKey): bool;

    /** @return list<Notification> newest first, older than $beforeId when given */
    public function latest(int $userId, ?int $beforeId, int $limit): array;

    public function unreadCount(int $userId): int;

    public function find(int $id, int $userId): ?Notification;

    public function markRead(int $id, int $userId): void;

    public function markAllRead(int $userId): void;

    public function emailEnabled(int $userId): bool;

    public function setEmailEnabled(int $userId, bool $enabled): void;
}
