<?php

declare(strict_types=1);

namespace App\Modules\Card\Domain;

use App\Modules\Card\Contract\CardSummary;

interface CardRepository
{
    /** @param array{description?: ?string, due_at?: ?string, completed_at?: ?string, created_at?: ?string} $extra */
    public function create(int $boardId, int $listId, string $title, string $position, int $createdBy, array $extra = []): CardSummary;

    public function find(int $id): ?CardSummary;

    public function findByPublicId(string $publicId): ?CardSummary;

    /** @param array{title?: string, description?: ?string, due_at?: ?string, completed_at?: ?string, list_id?: int, position?: string} $fields */
    public function update(int $id, array $fields): void;

    public function setArchived(int $id, bool $archived): void;

    public function delete(int $id): void;

    /** @return array<int, list<CardSummary>> */
    public function openCardsByList(int $boardId): array;

    /** @return list<CardSummary> */
    public function archivedCards(int $boardId): array;

    public function positionAfter(int $listId, ?int $afterCardId, ?int $movingId = null): string;

    public function positionAtEnd(int $listId): string;
}
