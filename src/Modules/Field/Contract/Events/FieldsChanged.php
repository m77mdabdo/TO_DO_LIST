<?php

declare(strict_types=1);

namespace App\Modules\Field\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** A board's custom fields were added, changed, reordered or deleted ($change), $fieldName for the activity line. */
final class FieldsChanged implements DomainEvent
{
    public function __construct(
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $change,
        public readonly string $fieldName,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'field.definitions_changed';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
