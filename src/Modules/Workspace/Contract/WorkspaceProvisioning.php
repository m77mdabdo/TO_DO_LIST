<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contract;

interface WorkspaceProvisioning
{
    /**
     * Makes sure the user has at least one workspace and returns the id of
     * their first one. Idempotent: costs one indexed lookup when they do.
     */
    public function ensureWorkspaceFor(int $userId, string $userName): int;
}
