<?php

declare(strict_types=1);

namespace App\Shared\Export;

use Closure;

/**
 * What each module adds to a board's JSON export, per card: labels,
 * checklists, comments, attachments. The board module asks for the cards
 * it is exporting; a part never widens what the user may see.
 */
final class ExportParts
{
    /** @var array<string, Closure(list<int>): array<int, mixed>> */
    private array $parts = [];

    /** @param Closure(list<int>): array<int, mixed> $forCards card ids => data per card id */
    public function add(string $name, Closure $forCards): void
    {
        $this->parts[$name] = $forCards;
    }

    /**
     * @param list<int> $cardIds
     * @return array<int, array<string, mixed>> card id => part name => data
     */
    public function forCards(array $cardIds): array
    {
        $out = [];
        foreach ($this->parts as $name => $part) {
            foreach ($part($cardIds) as $cardId => $data) {
                $out[$cardId][$name] = $data;
            }
        }

        return $out;
    }
}
