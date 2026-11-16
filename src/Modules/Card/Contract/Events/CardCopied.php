<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract\Events;

use App\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * A card was copied (into its own board or another one); other modules copy
 * what they keep on it, but only the parts the person asked for.
 */
final class CardCopied implements DomainEvent
{
    /** Parts other modules keep: labels (Label module), checklists (Checklist module). */
    public const PARTS = ['labels', 'checklists', 'fields'];

    /** @param list<string> $parts which of PARTS to copy */
    public function __construct(
        public readonly int $sourceCardId,
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $actorId,
        private readonly DateTimeImmutable $at,
        public readonly array $parts = self::PARTS,
    ) {
    }

    public function copies(string $part): bool
    {
        return in_array($part, $this->parts, true);
    }

    public static function name(): string
    {
        return 'card.copied';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }
}
