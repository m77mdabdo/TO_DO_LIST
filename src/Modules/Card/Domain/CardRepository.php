<?php

declare(strict_types=1);

namespace App\Modules\Card\Domain;

use App\Modules\Card\Contract\CardSummary;

interface CardRepository
{
    /** @param array{description?: ?string, due_at?: ?string, completed_at?: ?string, created_at?: ?string} $extra */
    public function create(int $boardId, int $listId, string $title, string $position, int $createdBy, array $extra = []): CardSummary;

    public function find(int $id): ?CardSummary;

    /**
     * @param list<int> $ids
     * @return array<int, CardSummary> by id
     */
    public function findMany(array $ids): array;

    /**
     * Open cards on these boards whose start or due date falls in [from, to].
     *
     * @param list<int> $boardIds
     * @return list<CardSummary>
     */
    public function scheduledBetween(array $boardIds, string $from, string $to): array;

    public function findByPublicId(string $publicId): ?CardSummary;

    /** @param array{title?: string, description?: ?string, start_at?: ?string, due_at?: ?string, completed_at?: ?string, list_id?: int, position?: string} $fields */
    public function update(int $id, array $fields): void;

    public function setArchived(int $id, bool $archived): void;

    public function delete(int $id): void;

    /** @return array<int, list<CardSummary>> */
    public function openCardsByList(int $boardId): array;

    /**
     * @param list<int> $boardIds
     * @return list<CardSummary>
     */
    public function search(array $boardIds, string $text, int $limit): array;

    /** @return list<CardSummary> */
    public function dueBetween(string $from, string $to): array;

    /** @return list<CardSummary> */
    public function archivedCards(int $boardId): array;

    /** @return list<int> user ids assigned to the card */
    public function membersOf(int $cardId): array;

    /**
     * @param list<int> $cardIds
     * @return array<int, list<int>> card id => user ids
     */
    public function membersForCards(array $cardIds): array;

    /** @return bool false when the user was already on the card */
    public function addMember(int $cardId, int $userId): bool;

    /** @return bool false when the user wasn't on the card */
    public function removeMember(int $cardId, int $userId): bool;

    public function positionAfter(int $listId, ?int $afterCardId, ?int $movingId = null): string;

    public function positionAtEnd(int $listId): string;
}
