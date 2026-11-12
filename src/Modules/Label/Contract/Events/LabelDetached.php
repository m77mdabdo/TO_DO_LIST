<?php

declare(strict_types=1);

namespace App\Modules\Label\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class LabelDetached implements DomainEvent
{
    public function __construct(
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $labelName,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'label.detached';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
