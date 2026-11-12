<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class BoardCreated implements DomainEvent
{
    public function __construct(
        public readonly int $boardId,
        public readonly int $workspaceId,
        public readonly int $actorId,
        public readonly string $boardName,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'board.created';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
