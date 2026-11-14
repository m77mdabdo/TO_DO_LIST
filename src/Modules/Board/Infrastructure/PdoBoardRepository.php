<?php

declare(strict_types=1);

namespace App\Modules\Board\Infrastructure;

use App\Database\OrderedRows;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Board\Contract\ListSummary;
use App\Modules\Board\Domain\BoardRepository;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use PDO;

final class PdoBoardRepository implements BoardRepository
{
    private const BOARD_COLUMNS = 'id, public_id, workspace_id, name, description, color, visibility, commenting, voting, is_template, archived_at';
    private const LIST_COLUMNS = 'id, board_id, name, position, archived_at';

    /** Columns update() may touch: an allow-list, never names from the request. */
    private const UPDATABLE = ['name', 'description', 'color', 'visibility', 'commenting', 'voting', 'is_template'];

    private OrderedRows $listOrder;

    /** @var array<int, ?BoardSummary> boards already loaded in this request */
    private array $byId = [];

    public function __construct(private PDO $db, private Clock $clock)
    {
        $this->listOrder = new OrderedRows($db, 'lists', 'board_id');
    }

    public function create(int $workspaceId, string $name, string $color, BoardVisibility $visibility, int $createdBy): BoardSummary
    {
        $now = $this->now();
        $this->db->prepare('INSERT INTO boards (public_id, workspace_id, name, color, visibility, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([Ulid::generate(), $workspaceId, $name, $color, $visibility->value, $createdBy, $now, $now]);

        return $this->find((int) $this->db->lastInsertId());
    }

    public function find(int $id): ?BoardSummary
    {
        if (!array_key_exists($id, $this->byId)) {
            $stmt = $this->db->prepare('SELECT ' . self::BOARD_COLUMNS . ' FROM boards WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $this->byId[$id] = $row ? BoardSummary::fromRow($row) : null;
        }

        return $this->byId[$id];
    }

    public function findByPublicId(string $publicId): ?BoardSummary
    {
        $stmt = $this->db->prepare('SELECT ' . self::BOARD_COLUMNS . ' FROM boards WHERE public_id = ?');
        $stmt->execute([$publicId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->byId[(int) $row['id']] = BoardSummary::fromRow($row);
    }

    public function update(int $id, array $fields): void
    {
        $fields = array_intersect_key($fields, array_flip(self::UPDATABLE));

        if ($fields === []) {
            return;
        }

        $sets = implode(', ', array_map(static fn (string $column) => "$column = ?", array_keys($fields)));
        $this->db->prepare("UPDATE boards SET $sets, updated_at = ? WHERE id = ?")
            ->execute([...array_values($fields), $this->now(), $id]);
        unset($this->byId[$id]);
    }

    public function setArchived(int $id, bool $archived): void
    {
        $this->db->prepare('UPDATE boards SET archived_at = ?, updated_at = ? WHERE id = ?')
            ->execute([$archived ? $this->now() : null, $this->now(), $id]);
        unset($this->byId[$id]);
    }

    public function visibleInWorkspace(int $workspaceId, bool $seesAll, bool $seesWorkspaceBoards, array $memberOf, bool $archived = false): array
    {
        $where = ['workspace_id = ?', $archived ? 'archived_at IS NOT NULL' : 'archived_at IS NULL'];
        $params = [$workspaceId];

        if (!$seesAll) {
            $or = [];

            if ($seesWorkspaceBoards) {
                $or[] = "visibility = 'workspace'";
            }

            if ($memberOf !== []) {
                $or[] = 'id IN (' . implode(',', array_fill(0, count($memberOf), '?')) . ')';
                array_push($params, ...$memberOf);
            }

            if ($or === []) {
                return [];
            }

            $where[] = '(' . implode(' OR ', $or) . ')';
        }

        $stmt = $this->db->prepare('SELECT ' . self::BOARD_COLUMNS . ' FROM boards WHERE ' . implode(' AND ', $where) . ' ORDER BY name, id');
        $stmt->execute($params);

        return array_map([BoardSummary::class, 'fromRow'], $stmt->fetchAll());
    }

    public function createList(int $boardId, string $name, string $position): ListSummary
    {
        $now = $this->now();
        $this->db->prepare('INSERT INTO lists (board_id, name, position, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$boardId, $name, $position, $now, $now]);

        return $this->findList((int) $this->db->lastInsertId(), $boardId);
    }

    public function findList(int $listId, int $boardId): ?ListSummary
    {
        $stmt = $this->db->prepare('SELECT ' . self::LIST_COLUMNS . ' FROM lists WHERE id = ? AND board_id = ?');
        $stmt->execute([$listId, $boardId]);
        $row = $stmt->fetch();

        return $row ? ListSummary::fromRow($row) : null;
    }

    public function updateList(int $listId, array $fields): void
    {
        $fields = array_intersect_key($fields, ['name' => true, 'position' => true]);

        if ($fields === []) {
            return;
        }

        $sets = implode(', ', array_map(static fn (string $column) => "$column = ?", array_keys($fields)));
        $this->db->prepare("UPDATE lists SET $sets, updated_at = ? WHERE id = ?")
            ->execute([...array_values($fields), $this->now(), $listId]);
    }

    public function setListArchived(int $listId, bool $archived): void
    {
        $this->db->prepare('UPDATE lists SET archived_at = ?, updated_at = ? WHERE id = ?')
            ->execute([$archived ? $this->now() : null, $this->now(), $listId]);
    }

    public function lists(int $boardId, bool $archived = false): array
    {
        $stmt = $this->db->prepare('SELECT ' . self::LIST_COLUMNS . ' FROM lists WHERE board_id = ? AND archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL') . ' ORDER BY position, id');
        $stmt->execute([$boardId]);

        return array_map([ListSummary::class, 'fromRow'], $stmt->fetchAll());
    }

    public function listPositionAfter(int $boardId, ?int $afterListId, ?int $movingId = null): string
    {
        return $this->listOrder->positionAfter($boardId, $afterListId, $movingId);
    }

    public function listPositionAtEnd(int $boardId): string
    {
        return $this->listOrder->positionAtEnd($boardId);
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
