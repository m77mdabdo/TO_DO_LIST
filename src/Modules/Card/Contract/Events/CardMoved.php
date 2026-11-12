<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class CardMoved implements DomainEvent
{
    public function __construct(
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $title,
        public readonly int $fromListId,
        public readonly int $toListId,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'card.moved';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
