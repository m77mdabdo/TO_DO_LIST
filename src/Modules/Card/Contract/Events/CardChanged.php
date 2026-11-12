<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** change: renamed | described | due_changed | completed | reopened | archived | restored */
final class CardChanged implements DomainEvent
{
    public function __construct(
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $change,
        public readonly string $title,
        /** @var array<string, string|int|null> */
        public readonly array $details,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'card.changed';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
