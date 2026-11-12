<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** Someone voted for a card ($added) or took their vote back. */
final class VoteChanged implements DomainEvent
{
    public function __construct(
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly bool $added,
        public readonly string $cardTitle,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'vote.changed';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
