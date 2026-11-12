<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Infrastructure;

use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Modules\Workspace\Contract\WorkspaceSummary;
use App\Modules\Workspace\Domain\WorkspaceRepository;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use PDO;

final class PdoWorkspaceRepository implements WorkspaceRepository
{
    private const SUMMARY_SELECT = 'SELECT w.id, w.public_id, w.name, c.name AS company_name, m.role
        FROM workspace_members m
        JOIN workspaces w ON w.id = m.workspace_id
        JOIN companies c ON c.id = w.company_id';

    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function createCompany(string $companyName, string $workspaceName, int $ownerId): array
    {
        $now = $this->now();

        $this->db->prepare('INSERT INTO companies (public_id, name, created_by, created_at) VALUES (?, ?, ?, ?)')
            ->execute([Ulid::generate(), $companyName, $ownerId, $now]);
        $companyId = (int) $this->db->lastInsertId();

        $this->db->prepare('INSERT INTO workspaces (public_id, company_id, name, created_at) VALUES (?, ?, ?, ?)')
            ->execute([Ulid::generate(), $companyId, $workspaceName, $now]);
        $workspaceId = (int) $this->db->lastInsertId();

        $this->addMember($workspaceId, $ownerId, WorkspaceRole::Owner);

        return ['companyId' => $companyId, 'workspaceId' => $workspaceId];
    }

    public function addMember(int $workspaceId, int $userId, WorkspaceRole $role): void
    {
        $this->db->prepare('INSERT INTO workspace_members (workspace_id, user_id, role, joined_at) VALUES (?, ?, ?, ?)')
            ->execute([$workspaceId, $userId, $role->value, $this->now()]);
    }

    public function firstWorkspaceIdOf(int $userId): ?int
    {
        $stmt = $this->db->prepare('SELECT workspace_id FROM workspace_members WHERE user_id = ? ORDER BY joined_at, workspace_id LIMIT 1');
        $stmt->execute([$userId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function summariesOf(int $userId): array
    {
        $stmt = $this->db->prepare(self::SUMMARY_SELECT . ' WHERE m.user_id = ? ORDER BY m.joined_at, w.id');
        $stmt->execute([$userId]);

        return array_map([$this, 'summary'], $stmt->fetchAll());
    }

    public function role(int $userId, int $workspaceId): ?WorkspaceRole
    {
        $stmt = $this->db->prepare('SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ?');
        $stmt->execute([$workspaceId, $userId]);
        $role = $stmt->fetchColumn();

        return $role === false ? null : WorkspaceRole::from((string) $role);
    }

    public function findForUser(string $publicId, int $userId): ?WorkspaceSummary
    {
        $stmt = $this->db->prepare(self::SUMMARY_SELECT . ' WHERE w.public_id = ? AND m.user_id = ?');
        $stmt->execute([$publicId, $userId]);
        $row = $stmt->fetch();

        return $row ? $this->summary($row) : null;
    }

    public function members(int $workspaceId): array
    {
        $stmt = $this->db->prepare('SELECT user_id, role FROM workspace_members WHERE workspace_id = ? ORDER BY joined_at, user_id');
        $stmt->execute([$workspaceId]);

        return array_map(
            static fn (array $row) => ['userId' => (int) $row['user_id'], 'role' => WorkspaceRole::from((string) $row['role'])],
            $stmt->fetchAll(),
        );
    }

    /** @param array<string, mixed> $row */
    private function summary(array $row): WorkspaceSummary
    {
        return new WorkspaceSummary(
            (int) $row['id'],
            (string) $row['public_id'],
            (string) $row['name'],
            (string) $row['company_name'],
            WorkspaceRole::from((string) $row['role']),
        );
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
