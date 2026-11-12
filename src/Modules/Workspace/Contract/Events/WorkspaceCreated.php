<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class WorkspaceCreated implements DomainEvent
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $companyId,
        public readonly int $ownerId,
        public readonly string $name,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'workspace.created';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
