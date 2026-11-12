<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** A card was permanently deleted. Listeners clean up what they keep for it. */
final class CardDeleted implements DomainEvent
{
    public function __construct(
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $title,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'card.deleted';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
