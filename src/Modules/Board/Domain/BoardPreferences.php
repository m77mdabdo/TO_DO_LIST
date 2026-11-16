<?php

declare(strict_types=1);

namespace App\Modules\Board\Domain;

use App\Modules\Board\Contract\CardDisplay;

/** One person's settings for one board. */
interface BoardPreferences
{
    public function cardDisplay(int $userId, int $boardId): CardDisplay;

    public function saveCardDisplay(int $userId, int $boardId, CardDisplay $display): void;

    /** @return list<int> the lists this person folded away on the board */
    public function collapsedLists(int $userId, int $boardId): array;

    /** @param list<int> $listIds */
    public function saveCollapsedLists(int $userId, int $boardId, array $listIds): void;
}
