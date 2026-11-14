<?php

declare(strict_types=1);

namespace App\Modules\Attachment\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** A file or link was added to a card. */
final class AttachmentAdded implements DomainEvent
{
    public function __construct(
        public readonly int $attachmentId,
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $cardTitle,
        public readonly string $name,
        public readonly bool $isLink,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'attachment.added';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
