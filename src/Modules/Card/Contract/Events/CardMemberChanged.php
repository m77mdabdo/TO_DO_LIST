<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** Someone was assigned to a card ($added) or taken off it. */
final class CardMemberChanged implements DomainEvent
{
    public function __construct(
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly int $memberId,
        public readonly bool $added,
        public readonly string $title,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'card.member_changed';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
