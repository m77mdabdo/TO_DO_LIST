<?php

declare(strict_types=1);

namespace App\Modules\Field\Domain;

use App\Modules\Field\Contract\CustomField;

interface FieldRepository
{
    /** @return list<CustomField> in board order */
    public function forBoard(int $boardId): array;

    public function find(int $fieldId, int $boardId): ?CustomField;

    public function findByName(int $boardId, string $name): ?CustomField;

    /** @param list<array{id: string, name: string}> $options */
    public function create(int $boardId, string $name, string $type, array $options, bool $onCard): CustomField;

    /** @param list<array{id: string, name: string}> $options */
    public function update(int $fieldId, string $name, array $options, bool $onCard): void;

    /** @param list<int> $orderedIds every field of the board, in the new order */
    public function reorder(int $boardId, array $orderedIds): void;

    public function delete(int $fieldId): void;

    /** How many cards hold a value for the field (or for one of its choices). */
    public function valueCount(int $fieldId, ?string $value = null): int;

    /**
     * @param list<int> $cardIds
     * @return array<int, array<int, string>> card id => field id => stored value
     */
    public function valuesForCards(array $cardIds): array;

    public function setValue(int $cardId, int $fieldId, string $value): void;

    public function clearValue(int $cardId, int $fieldId): void;

    /** Removes the values that point at choices that no longer exist. */
    public function clearValuesNotIn(int $fieldId, array $keep): void;
}
