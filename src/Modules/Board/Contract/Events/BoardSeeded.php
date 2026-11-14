<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** A new board was started from a built-in template; these labels go with it. */
final class BoardSeeded implements DomainEvent
{
    /** @param list<array{0: string, 1: string}> $labels name and color */
    public function __construct(
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $template,
        public readonly array $labels,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'board.seeded';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
