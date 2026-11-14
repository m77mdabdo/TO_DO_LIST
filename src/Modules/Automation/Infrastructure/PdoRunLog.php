<?php

declare(strict_types=1);

namespace App\Modules\Automation\Infrastructure;

use App\Modules\Automation\Contract\AutomationRun;
use App\Modules\Automation\Domain\RunLog;
use App\Support\Clock;
use PDO;
use PDOException;

final class PdoRunLog implements RunLog
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function add(int $automationId, int $boardId, ?int $cardId, ?int $actorId, string $status, string $detail, ?string $dedupeKey = null): bool
    {
        try {
            $this->db->prepare('INSERT INTO automation_runs (automation_id, board_id, card_id, actor_id, status, detail, dedupe_key, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$automationId, $boardId, $cardId, $actorId, $status, mb_substr($detail, 0, 500), $dedupeKey, $this->clock->now()->format('Y-m-d H:i:s')]);
        } catch (PDOException $e) {
            // 23000: that dedupe key is taken, so this run already happened.
            if ((string) $e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }

        return true;
    }

    public function hasRun(string $dedupeKey): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM automation_runs WHERE dedupe_key = ?');
        $stmt->execute([$dedupeKey]);

        return $stmt->fetchColumn() !== false;
    }

    public function latest(int $boardId, int $limit = 50): array
    {
        $stmt = $this->db->prepare('SELECT r.*, a.name AS automation_name FROM automation_runs r JOIN automations a ON a.id = r.automation_id
            WHERE r.board_id = ? ORDER BY r.id DESC LIMIT ' . max(1, $limit));
        $stmt->execute([$boardId]);

        return array_map([AutomationRun::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function pruneBefore(string $before): int
    {
        $stmt = $this->db->prepare('DELETE FROM automation_runs WHERE created_at < ?');
        $stmt->execute([$before]);

        return $stmt->rowCount();
    }
}
