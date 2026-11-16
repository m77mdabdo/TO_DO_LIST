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
    public function addComment(int $cardId, int $boardId, int $authorId, string $body, array $mentions, ?int $attachmentId = null): int;

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

    /** @return bool true when the reaction was added, false when it was there and is now removed */
    public function toggleReaction(int $commentId, int $userId, string $emoji): bool;

    /** Marks the user as writing a comment on the card now, and forgets rows older than $staleBefore. */
    public function touchTyping(int $cardId, int $userId, string $staleBefore): void;

    /** Stops showing the user as writing on the card (the comment was sent). */
    public function stopTyping(int $cardId, int $userId): void;

    /** @return list<int> people who typed on the card at or after $since */
    public function typingOn(int $cardId, string $since): array;
}
