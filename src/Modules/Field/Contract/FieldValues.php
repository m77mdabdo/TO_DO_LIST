<?php

declare(strict_types=1);

namespace App\Modules\Field\Contract;

use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardSummary;

/** Custom fields and their values for other modules (the API). */
interface FieldValues
{
    /** @return list<CustomField> */
    public function forBoard(int $boardId): array;

    /**
     * @param list<int> $cardIds
     * @return array<int, array<int, string>> card id => field id => stored value
     */
    public function valuesForCards(array $cardIds): array;

    /**
     * Sets values (field id => raw value, "" clears) with the card page's checks. Returns how many changed.
     *
     * @param array<int|string, mixed> $input
     * @throws \App\Validation\ValidationException
     */
    public function fill(int $actorId, BoardSummary $board, CardSummary $card, array $input): int;
}
