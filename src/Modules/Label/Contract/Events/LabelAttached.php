<?php

declare(strict_types=1);

namespace App\Modules\Label\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class LabelAttached implements DomainEvent
{
    public function __construct(
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $labelName,
        private readonly DateTimeImmutable $at,
        public readonly int $labelId = 0,
    ) {
    }

    public static function name(): string
    {
        return 'label.attached';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
