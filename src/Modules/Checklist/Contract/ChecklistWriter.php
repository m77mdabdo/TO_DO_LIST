<?php

declare(strict_types=1);

namespace App\Modules\Checklist\Contract;

use App\Modules\Card\Contract\CardSummary;

/** Building checklists from other modules (imports), with the same checks as the card page. */
interface ChecklistWriter
{
    /** @return list<Checklist> */
    public function forCard(int $cardId): array;

    /** Returns the new checklist's id. */
    public function addChecklist(int $actorId, CardSummary $card, string $title): int;

    /** Adds one item per line of $text. */
    public function addItem(int $actorId, int $checklistId, string $text): CardSummary;

    /** Ticks or unticks an item; returns whether it is now done. */
    public function toggleItem(int $actorId, int $itemId): bool;
}
