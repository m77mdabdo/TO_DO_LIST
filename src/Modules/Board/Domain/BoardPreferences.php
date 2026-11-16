<?php

declare(strict_types=1);

namespace App\Modules\Board\Domain;

use App\Modules\Board\Contract\CardDisplay;

/** One person's settings for one board. */
interface BoardPreferences
{
    public function cardDisplay(int $userId, int $boardId): CardDisplay;

    public function saveCardDisplay(int $userId, int $boardId, CardDisplay $display): void;
}
