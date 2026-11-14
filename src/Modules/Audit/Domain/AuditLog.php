<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

use App\Modules\Audit\Contract\AuditEntry;

/** Append-only: entries are added, never changed or removed. */
interface AuditLog
{
    /** @param array<string, string> $details */
    public function add(int $workspaceId, ?int $actorId, string $action, string $target, array $details = []): void;

    /** @return list<AuditEntry> newest first */
    public function forWorkspace(int $workspaceId, ?int $beforeId, int $limit): array;
}
