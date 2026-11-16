<?php

declare(strict_types=1);

namespace App\Modules\Field\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/** A card's value for a custom field was set ($value as people read it) or cleared ($value === ''). */
final class FieldValueChanged implements DomainEvent
{
    public function __construct(
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        public readonly string $cardTitle,
        public readonly string $fieldName,
        public readonly string $value,
        private readonly DateTimeImmutable $at,
    ) {
    }

    public static function name(): string
    {
        return 'field.value_changed';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
