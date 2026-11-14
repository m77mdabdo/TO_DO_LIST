<?php

declare(strict_types=1);

namespace App\Modules\Access\Contract\Events;

use App\Modules\Access\Contract\BoardRole;
use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Someone was added to a board ($from null), had their role changed, or
 * was taken off it ($to null).
 */
final class BoardMemberChanged implements DomainEvent
{
    public function __construct(
        public readonly int $boardId,
        public readonly int $workspaceId,
        public readonly int $userId,
        public readonly int $actorId,
        public readonly ?BoardRole $from,
        public readonly ?BoardRole $to,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'board.member_changed';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
