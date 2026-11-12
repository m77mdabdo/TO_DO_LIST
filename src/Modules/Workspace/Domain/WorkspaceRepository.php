<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain;

use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Modules\Workspace\Contract\WorkspaceSummary;

interface WorkspaceRepository
{
    /** @return array{companyId: int, workspaceId: int} */
    public function createCompany(string $companyName, string $workspaceName, int $ownerId): array;

    public function addMember(int $workspaceId, int $userId, WorkspaceRole $role): void;

    public function firstWorkspaceIdOf(int $userId): ?int;

    /** @return list<WorkspaceSummary> */
    public function summariesOf(int $userId): array;

    public function role(int $userId, int $workspaceId): ?WorkspaceRole;

    public function findForUser(string $publicId, int $userId): ?WorkspaceSummary;

    /** @return list<array{userId: int, role: WorkspaceRole}> */
    public function members(int $workspaceId): array;
}
