<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** A card was copied into another board; other modules copy what they keep on it (labels, checklists). */
final class CardCopied implements DomainEvent
{
    public function __construct(
        public readonly int $sourceCardId,
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'card.copied';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
