<?php

declare(strict_types=1);

namespace App\Modules\Checklist\Contract;

final class Checklist
{
    /** @param list<ChecklistItem> $items in order */
    public function __construct(
        public readonly int $id,
        public readonly int $cardId,
        public readonly string $title,
        public readonly array $items = [],
    ) {
    }

    public function doneCount(): int
    {
        return count(array_filter($this->items, static fn (ChecklistItem $item) => $item->isDone()));
    }

    /** Whole percent done, 0 for an empty list. */
    public function percent(): int
    {
        return $this->items === [] ? 0 : intdiv($this->doneCount() * 100, count($this->items));
    }
}
