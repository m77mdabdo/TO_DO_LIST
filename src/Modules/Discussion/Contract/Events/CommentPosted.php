<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** A new comment. $mentionedUserIds are the people @mentioned in it (for notifications). */
final class CommentPosted implements DomainEvent
{
    /** @param list<int> $mentionedUserIds */
    public function __construct(
        public readonly int $commentId,
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $cardTitle,
        public readonly string $excerpt,
        public readonly array $mentionedUserIds,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'comment.posted';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
