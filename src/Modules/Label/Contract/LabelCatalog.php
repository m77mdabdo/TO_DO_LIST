<?php

declare(strict_types=1);

namespace App\Modules\Label\Contract;

interface LabelCatalog
{
    /** @return list<Label> */
    public function forBoard(int $boardId): array;

    /**
     * Labels of many cards in ONE query, as a hash map card id => labels.
     *
     * @param list<int> $cardIds
     * @return array<int, list<Label>>
     */
    public function forCards(array $cardIds): array;

    /** Finds the board's label with that name, or creates it. Needs ManageLabels. */
    public function ensureLabel(int $actorId, int $boardId, string $name, string $color): Label;

    /** Needs EditCards on the card's board. */
    public function attach(int $actorId, int $cardId, int $labelId): void;
}
