<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** change: renamed | archived | restored | visibility | recolored | described */
final class BoardChanged implements DomainEvent
{
    public function __construct(
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $change,
        /** @var array<string, string|int|null> */
        public readonly array $details,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'board.changed';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
