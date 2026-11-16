<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * A comment was edited, deleted, or someone reacted to it ($change is
 * "edited", "deleted", "reacted" or "unreacted"; $emoji is set for reactions).
 * Open board and card pages use it to know something changed.
 */
final class CommentChanged implements DomainEvent
{
    public function __construct(
        public readonly int $commentId,
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $change,
        public readonly ?string $emoji,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'comment.changed';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
