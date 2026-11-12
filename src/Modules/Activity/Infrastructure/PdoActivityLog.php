<?php

declare(strict_types=1);

namespace App\Modules\Activity\Infrastructure;

use App\Modules\Activity\Contract\ActivityEntry;
use App\Modules\Activity\Domain\ActivityLog;
use App\Support\Clock;
use PDO;

final class PdoActivityLog implements ActivityLog
{
    private const MAX_PAGE = 100;

    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function record(int $boardId, ?int $cardId, int $actorId, string $type, string $subject, array $data = []): void
    {
        $this->db->prepare('INSERT INTO activities (board_id, card_id, actor_id, type, subject, data, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $boardId, $cardId, $actorId, $type, mb_substr($subject, 0, 200),
                json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $this->clock->now()->format('Y-m-d H:i:s'),
            ]);
    }

    public function forBoard(int $boardId, ?int $beforeId = null, int $limit = 30): array
    {
        return $this->page('board_id', $boardId, $beforeId, $limit);
    }

    public function forCard(int $cardId, ?int $beforeId = null, int $limit = 30): array
    {
        return $this->page('card_id', $cardId, $beforeId, $limit);
    }

    /** @return list<ActivityEntry> */
    private function page(string $column, int $id, ?int $beforeId, int $limit): array
    {
        $limit = max(1, min($limit, self::MAX_PAGE));
        $sql = "SELECT id, board_id, card_id, actor_id, type, subject, data, created_at FROM activities WHERE $column = ?"
            . ($beforeId !== null ? ' AND id < ?' : '')
            . ' ORDER BY id DESC LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($beforeId !== null ? [$id, $beforeId] : [$id]);

        return array_map([ActivityEntry::class, 'fromRow'], $stmt->fetchAll());
    }
}
