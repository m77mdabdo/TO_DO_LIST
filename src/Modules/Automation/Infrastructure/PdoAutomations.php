<?php

declare(strict_types=1);

namespace App\Modules\Automation\Infrastructure;

use App\Modules\Automation\Contract\Automation;
use App\Modules\Automation\Domain\AutomationRepository;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use PDO;

final class PdoAutomations implements AutomationRepository
{
    private const FIELDS = ['board_id', 'kind', 'name', 'trigger_type', 'trigger_value', 'scope_list_id', 'conditions', 'actions', 'schedule', 'created_by'];

    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function create(array $fields): Automation
    {
        $publicId = Ulid::generate();
        $values = array_map(static fn (string $f) => $fields[$f] ?? null, self::FIELDS);
        $this->db->prepare('INSERT INTO automations (public_id, ' . implode(', ', self::FIELDS) . ', created_at) VALUES (?, ' . implode(', ', array_fill(0, count(self::FIELDS), '?')) . ', ?)')
            ->execute([$publicId, ...$values, $this->now()]);

        return $this->findByPublicId($publicId) ?? throw new \LogicException('Automation not saved.');
    }

    public function findByPublicId(string $publicId): ?Automation
    {
        $stmt = $this->db->prepare('SELECT * FROM automations WHERE public_id = ?');
        $stmt->execute([$publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Automation::fromRow($row) : null;
    }

    public function forBoard(int $boardId): array
    {
        return $this->many('SELECT * FROM automations WHERE board_id = ? ORDER BY id', [$boardId]);
    }

    public function countForBoard(int $boardId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM automations WHERE board_id = ?');
        $stmt->execute([$boardId]);

        return (int) $stmt->fetchColumn();
    }

    public function rulesFor(int $boardId, string $trigger): array
    {
        return $this->many("SELECT * FROM automations WHERE board_id = ? AND kind = 'rule' AND trigger_type = ? AND enabled = 1 ORDER BY id", [$boardId, $trigger]);
    }

    public function enabledOfKind(string $kind): array
    {
        return $this->many('SELECT a.* FROM automations a JOIN boards b ON b.id = a.board_id WHERE a.kind = ? AND a.enabled = 1 AND b.archived_at IS NULL ORDER BY a.id', [$kind]);
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $this->db->prepare('UPDATE automations SET enabled = ?, failures = 0 WHERE id = ?')->execute([$enabled ? 1 : 0, $id]);
    }

    public function recordRun(int $id, bool $ok): int
    {
        $this->db->prepare('UPDATE automations SET last_run_at = ?, failures = ' . ($ok ? '0' : 'failures + 1') . ' WHERE id = ?')->execute([$this->now(), $id]);
        $stmt = $this->db->prepare('SELECT failures FROM automations WHERE id = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM automations WHERE id = ?')->execute([$id]);
    }

    /**
     * @param list<mixed> $params
     * @return list<Automation>
     */
    private function many(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([Automation::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
