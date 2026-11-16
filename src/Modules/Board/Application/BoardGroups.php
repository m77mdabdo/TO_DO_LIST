<?php

declare(strict_types=1);

namespace App\Modules\Board\Application;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use PDO;

/**
 * Groups that organize a workspace's boards on the home page. The owner and
 * admins of the workspace create, rename, reorder and delete groups; whoever
 * manages a board's settings puts it in a group. Deleting a group keeps its
 * boards, which move to "Other boards". People only ever see the boards
 * they could see anyway; a group shows no board names to anyone else.
 */
final class BoardGroups
{
    public const MAX = 30;

    public function __construct(
        private PDO $db,
        private WorkspaceDirectory $workspaces,
        private AccessGate $gate,
        private Clock $clock,
    ) {
    }

    /** @return list<array{id: int, name: string}> in their order */
    public function inWorkspace(int $workspaceId): array
    {
        $stmt = $this->db->prepare('SELECT id, name FROM board_groups WHERE workspace_id = ? ORDER BY position, id');
        $stmt->execute([$workspaceId]);

        return array_map(static fn (array $row) => ['id' => (int) $row['id'], 'name' => (string) $row['name']], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param list<int> $boardIds
     * @return array<int, int> board id => group id, for boards that are in a group
     */
    public function groupOfBoards(array $boardIds): array
    {
        if ($boardIds === []) {
            return [];
        }
        $marks = implode(', ', array_fill(0, count($boardIds), '?'));
        $stmt = $this->db->prepare("SELECT id, group_id FROM boards WHERE id IN ($marks) AND group_id IS NOT NULL");
        $stmt->execute(array_values($boardIds));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['id']] = (int) $row['group_id'];
        }

        return $out;
    }

    public function canManage(int $userId, int $workspaceId): bool
    {
        return $this->workspaces->roleOf($userId, $workspaceId)?->managesAllBoards() ?? false;
    }

    public function create(int $actorId, int $workspaceId, string $name): void
    {
        $this->assertManager($actorId, $workspaceId);
        $name = $this->validName($name);
        if (count($this->inWorkspace($workspaceId)) >= self::MAX) {
            $this->fail('group_name', 'A workspace can have up to 30 board groups.');
        }
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(position), 0) FROM board_groups WHERE workspace_id = ?');
        $stmt->execute([$workspaceId]);
        $this->db->prepare('INSERT INTO board_groups (workspace_id, name, position, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$workspaceId, $name, (int) $stmt->fetchColumn() + 1, $this->clock->now()->format('Y-m-d H:i:s')]);
    }

    public function rename(int $actorId, int $workspaceId, int $groupId, string $name): void
    {
        $this->assertManager($actorId, $workspaceId);
        $this->assertInWorkspace($groupId, $workspaceId);
        $this->db->prepare('UPDATE board_groups SET name = ? WHERE id = ?')->execute([$this->validName($name), $groupId]);
    }

    /** Moves the group one place up or down. */
    public function move(int $actorId, int $workspaceId, int $groupId, bool $up): void
    {
        $this->assertManager($actorId, $workspaceId);
        $this->assertInWorkspace($groupId, $workspaceId);
        $ids = array_column($this->inWorkspace($workspaceId), 'id');
        $i = array_search($groupId, $ids, true);
        $j = $up ? $i - 1 : $i + 1;
        if ($i === false || !isset($ids[$j])) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $update = $this->db->prepare('UPDATE board_groups SET position = ? WHERE id = ?');
        foreach ($ids as $position => $id) {
            $update->execute([$position + 1, $id]);
        }
    }

    /** Deletes the group; its boards stay, outside any group. */
    public function delete(int $actorId, int $workspaceId, int $groupId): void
    {
        $this->assertManager($actorId, $workspaceId);
        $this->assertInWorkspace($groupId, $workspaceId);
        $this->db->prepare('UPDATE boards SET group_id = NULL WHERE group_id = ?')->execute([$groupId]);
        $this->db->prepare('DELETE FROM board_groups WHERE id = ?')->execute([$groupId]);
    }

    /** Puts the board in a group of its workspace, or in none (null). */
    public function place(int $actorId, BoardSummary $board, ?int $groupId): void
    {
        $this->gate->authorize($actorId, $board->id, Permission::ManageSettings);
        if ($board->archived) {
            throw AccessDenied::forbidden();
        }
        if ($groupId !== null && !in_array($groupId, array_column($this->inWorkspace($board->workspaceId), 'id'), true)) {
            $this->fail('group', 'Choose a group of this workspace.');
        }
        $this->db->prepare('UPDATE boards SET group_id = ? WHERE id = ?')->execute([$groupId, $board->id]);
    }

    private function assertManager(int $actorId, int $workspaceId): void
    {
        if ($this->workspaces->roleOf($actorId, $workspaceId) === null) {
            throw AccessDenied::hidden();
        }
        if (!$this->canManage($actorId, $workspaceId)) {
            throw AccessDenied::forbidden();
        }
    }

    private function assertInWorkspace(int $groupId, int $workspaceId): void
    {
        if (!in_array($groupId, array_column($this->inWorkspace($workspaceId), 'id'), true)) {
            throw AccessDenied::hidden();
        }
    }

    private function validName(string $name): string
    {
        $name = trim((string) preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $name));
        if ($name === '') {
            $this->fail('group_name', 'Give the group a name.');
        }
        if (mb_strlen($name) > 80) {
            $this->fail('group_name', 'The name may not be longer than 80 characters.');
        }

        return $name;
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
