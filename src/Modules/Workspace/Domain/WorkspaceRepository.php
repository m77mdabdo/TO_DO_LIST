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

    /** Remembers that the user opened this workspace, for the next sign-in. */
    public function markOpened(int $userId, int $workspaceId): void;

    /** The public id of the workspace the user opened most recently, if any. */
    public function lastOpenedOf(int $userId): ?string;

    /** @return list<WorkspaceSummary> */
    public function summariesOf(int $userId): array;

    public function role(int $userId, int $workspaceId): ?WorkspaceRole;

    public function findForUser(string $publicId, int $userId): ?WorkspaceSummary;

    /** @return list<array{userId: int, role: WorkspaceRole}> */
    public function members(int $workspaceId): array;
}
