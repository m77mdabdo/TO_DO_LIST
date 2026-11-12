<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class CardCreated implements DomainEvent
{
    public function __construct(
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $listId,
        public readonly int $actorId,
        public readonly string $title,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'card.created';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
