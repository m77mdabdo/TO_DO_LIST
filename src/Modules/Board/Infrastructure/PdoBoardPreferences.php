<?php

declare(strict_types=1);

namespace App\Modules\Board\Infrastructure;

use App\Modules\Board\Contract\CardDisplay;
use App\Modules\Board\Domain\BoardPreferences;
use App\Support\Clock;
use PDO;

final class PdoBoardPreferences implements BoardPreferences
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function cardDisplay(int $userId, int $boardId): CardDisplay
    {
        $stmt = $this->db->prepare('SELECT card_display FROM board_preferences WHERE user_id = ? AND board_id = ?');
        $stmt->execute([$userId, $boardId]);
        $json = $stmt->fetchColumn();

        return $json === false ? CardDisplay::all() : CardDisplay::fromJson((string) $json);
    }

    public function saveCardDisplay(int $userId, int $boardId, CardDisplay $display): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        // A delete and an insert work the same on SQLite and MySQL (no upsert dialects).
        $this->db->prepare('DELETE FROM board_preferences WHERE user_id = ? AND board_id = ?')->execute([$userId, $boardId]);
        $this->db->prepare('INSERT INTO board_preferences (user_id, board_id, card_display, updated_at) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $boardId, $display->toJson(), $now]);
    }
}
