<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * A board was made from another one (a copy, or a board made from a saved
 * template). The lists are already copied; $listMap tells other modules
 * where each old list went, so they can copy labels, cards and members.
 */
final class BoardCopied implements DomainEvent
{
    /** @param array<int, int> $listMap old list id => new list id */
    public function __construct(
        public readonly int $sourceBoardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly array $listMap,
        public readonly bool $withCards,
        public readonly bool $withMembers,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'board.copied';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
