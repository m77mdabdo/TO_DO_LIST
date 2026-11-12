<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class ListCreated implements DomainEvent
{
    public function __construct(
        public readonly int $listId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $listName,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'list.created';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
