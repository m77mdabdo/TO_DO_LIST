<?php

declare(strict_types=1);

namespace App\Shared\Events;

use DateTimeImmutable;

/**
 * Something that already happened inside one module and that other
 * modules may react to. Events are immutable facts: past tense names,
 * readonly data, no behaviour.
 */
interface DomainEvent
{
    /** A stable dotted name, e.g. "identity.user_registered". */
    public static function name(): string;

    public function occurredAt(): DateTimeImmutable;
}
