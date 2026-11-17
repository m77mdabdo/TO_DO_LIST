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

    /**
     * Entries matching the filters, newest first.
     *
     * @param array{actor?: ?int, action?: ?string, from?: ?string, to?: ?string} $filters action may end with "." to match a group (e.g. "member.")
     * @return list<\App\Modules\Audit\Contract\AuditEntry>
     */
    public function search(int $workspaceId, array $filters, ?int $beforeId, int $limit): array;

    /** @return list<string> the actions recorded in this workspace */
    public function actions(int $workspaceId): array;

    /** Deletes entries older than $days. @return int how many */
    public function prune(int $days): int;
}
