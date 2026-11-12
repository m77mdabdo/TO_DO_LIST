<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application;

use App\Http\HttpException;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Notification\Contract\Notification;
use App\Modules\Notification\Domain\NotificationStore;

/** A person's bell, their email setting, and what they watch. */
final class NotificationCenter
{
    public const PAGE = 30;

    public function __construct(
        private NotificationStore $store,
        private AccessGate $gate,
        private CardQueries $cards,
    ) {
    }

    /** @return list<Notification> */
    public function latest(int $userId, ?int $beforeId = null): array
    {
        return $this->store->latest($userId, $beforeId, self::PAGE);
    }

    public function unreadCount(int $userId): int
    {
        return $this->store->unreadCount($userId);
    }

    /** Marks it read and returns where it points (always a path inside the app). */
    public function open(int $userId, int $id): string
    {
        $notification = $this->store->find($id, $userId) ?? throw HttpException::notFound();
        $this->store->markRead($id, $userId);

        return preg_match('#^/[A-Za-z0-9/_\-]*(\#[A-Za-z0-9_\-]+)?$#', $notification->link) === 1 ? $notification->link : '/';
    }

    public function markAllRead(int $userId): void
    {
        $this->store->markAllRead($userId);
    }

    public function emailEnabled(int $userId): bool
    {
        return $this->store->emailEnabled($userId);
    }

    public function setEmailEnabled(int $userId, bool $enabled): void
    {
        $this->store->setEmailEnabled($userId, $enabled);
    }

    public function isWatchingCard(int $userId, int $cardId): bool
    {
        return $this->store->isWatching($userId, 'card', $cardId);
    }

    public function isWatchingBoard(int $userId, int $boardId): bool
    {
        return $this->store->isWatching($userId, 'board', $boardId);
    }

    /** Watches the card, or stops. Returns whether they now watch it. */
    public function toggleCard(int $userId, CardSummary $card): bool
    {
        $this->gate->authorize($userId, $card->boardId, Permission::ViewBoard);

        return $this->toggle($userId, 'card', $card->id, $card->boardId);
    }

    public function toggleBoard(int $userId, BoardSummary $board): bool
    {
        $this->gate->authorize($userId, $board->id, Permission::ViewBoard);

        return $this->toggle($userId, 'board', $board->id, $board->id);
    }

    public function cardByPublicId(string $publicId): ?CardSummary
    {
        return $this->cards->findByPublicId($publicId);
    }

    private function toggle(int $userId, string $target, int $targetId, int $boardId): bool
    {
        if ($this->store->isWatching($userId, $target, $targetId)) {
            $this->store->unwatch($userId, $target, $targetId);

            return false;
        }

        $this->store->watch($userId, $target, $targetId, $boardId);

        return true;
    }
}
