<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * An administrative change worth keeping in the workspace's audit log
 * (suspensions, policies, public links, ownership, account deletions).
 * Any module may publish it; the Audit module records it.
 */
final class WorkspaceAdminAction implements DomainEvent
{
    /** @param array<string, string> $details */
    public function __construct(
        public readonly int $workspaceId,
        public readonly ?int $actorId,
        public readonly string $action,
        public readonly string $target,
        public readonly array $details,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'workspace.admin_action';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
