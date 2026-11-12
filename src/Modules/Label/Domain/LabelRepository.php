<?php

declare(strict_types=1);

namespace App\Modules\Label\Domain;

use App\Modules\Label\Contract\Label;

interface LabelRepository
{
    /** @return list<Label> */
    public function forBoard(int $boardId): array;

    public function find(int $labelId, int $boardId): ?Label;

    public function findByName(int $boardId, string $name): ?Label;

    public function create(int $boardId, string $name, string $color): Label;

    /**
     * @param list<int> $cardIds
     * @return array<int, list<Label>>
     */
    public function forCards(array $cardIds): array;

    public function isAttached(int $cardId, int $labelId): bool;

    public function attach(int $cardId, int $labelId): void;

    public function detach(int $cardId, int $labelId): void;
}
