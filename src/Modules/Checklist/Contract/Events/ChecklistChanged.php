<?php

declare(strict_types=1);

namespace App\Modules\Checklist\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * change: added | renamed | removed (a checklist; $subject is its title)
 *       | item_completed | item_reopened (an item; $subject is its text)
 */
final class ChecklistChanged implements DomainEvent
{
    public function __construct(
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $change,
        public readonly string $subject,
        public readonly string $cardTitle,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'checklist.changed';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
