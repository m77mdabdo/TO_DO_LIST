<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contract;

/** What other modules may know about a workspace, seen by one user. */
final class WorkspaceSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly string $name,
        public readonly string $companyName,
        public readonly WorkspaceRole $role,
    ) {
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }
}
