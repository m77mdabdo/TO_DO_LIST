<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Domain;

use App\Modules\Discussion\Contract\Comment;

interface DiscussionRepository
{
    /**
     * Newest first; pass the last id shown as $beforeId for the next page.
     *
     * @return list<Comment>
     */
    public function commentsOn(int $cardId, ?int $beforeId, int $limit): array;

    public function findComment(int $id): ?Comment;

    /** @param list<int> $mentions */
    public function addComment(int $cardId, int $boardId, int $authorId, string $body, array $mentions): int;

    /** @param list<int> $mentions */
    public function editComment(int $id, string $body, array $mentions): void;

    public function deleteComment(int $id): void;

    /** @return list<int> user ids, in the order they voted */
    public function votersOf(int $cardId): array;

    /** @return bool false when the vote was already there */
    public function addVote(int $cardId, int $userId): bool;

    /** @return bool false when there was no vote to remove */
    public function removeVote(int $cardId, int $userId): bool;

    /**
     * Comment and vote counts per card, two grouped queries for any number of cards.
     *
     * @param list<int> $cardIds
     * @return array<int, array{comments: int, votes: int}>
     */
    public function countsForCards(array $cardIds): array;
}
