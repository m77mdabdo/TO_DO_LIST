<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Infrastructure;

use App\Modules\Discussion\Contract\Comment;
use App\Modules\Discussion\Domain\DiscussionRepository;
use App\Support\Clock;
use PDO;

final class PdoDiscussion implements DiscussionRepository
{
    private const COLUMNS = 'id, card_id, board_id, author_id, body, created_at, edited_at';

    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function commentsOn(int $cardId, ?int $beforeId, int $limit): array
    {
        $limit = max(1, min($limit, 100));
        $sql = 'SELECT ' . self::COLUMNS . ' FROM comments WHERE card_id = ?' . ($beforeId !== null ? ' AND id < ?' : '') . " ORDER BY id DESC LIMIT $limit";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($beforeId !== null ? [$cardId, $beforeId] : [$cardId]);
        $rows = $stmt->fetchAll();

        $mentions = $this->mentionsOf(array_map(static fn (array $row) => (int) $row['id'], $rows));

        return array_map(static fn (array $row) => Comment::fromRow($row, $mentions[(int) $row['id']] ?? []), $rows);
    }

    public function findComment(int $id): ?Comment
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM comments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Comment::fromRow($row, $this->mentionsOf([$id])[$id] ?? []) : null;
    }

    public function addComment(int $cardId, int $boardId, int $authorId, string $body, array $mentions): int
    {
        $this->db->prepare('INSERT INTO comments (card_id, board_id, author_id, body, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$cardId, $boardId, $authorId, $body, $this->now()]);
        $id = (int) $this->db->lastInsertId();
        $this->saveMentions($id, $mentions);

        return $id;
    }

    public function editComment(int $id, string $body, array $mentions): void
    {
        $this->db->prepare('UPDATE comments SET body = ?, edited_at = ? WHERE id = ?')->execute([$body, $this->now(), $id]);
        $this->db->prepare('DELETE FROM comment_mentions WHERE comment_id = ?')->execute([$id]);
        $this->saveMentions($id, $mentions);
    }

    public function deleteComment(int $id): void
    {
        $this->db->prepare('DELETE FROM comment_mentions WHERE comment_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
    }

    public function votersOf(int $cardId): array
    {
        $stmt = $this->db->prepare('SELECT user_id FROM card_votes WHERE card_id = ? ORDER BY created_at, user_id');
        $stmt->execute([$cardId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function addVote(int $cardId, int $userId): bool
    {
        $exists = $this->db->prepare('SELECT 1 FROM card_votes WHERE card_id = ? AND user_id = ?');
        $exists->execute([$cardId, $userId]);
        if ($exists->fetchColumn()) {
            return false;
        }

        $this->db->prepare('INSERT INTO card_votes (card_id, user_id, created_at) VALUES (?, ?, ?)')->execute([$cardId, $userId, $this->now()]);

        return true;
    }

    public function removeVote(int $cardId, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM card_votes WHERE card_id = ? AND user_id = ?');
        $stmt->execute([$cardId, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function countsForCards(array $cardIds): array
    {
        if ($cardIds === []) {
            return [];
        }

        $marks = implode(', ', array_fill(0, count($cardIds), '?'));
        $counts = [];

        foreach (['comments' => 'comments', 'votes' => 'card_votes'] as $key => $table) {
            $stmt = $this->db->prepare("SELECT card_id, COUNT(*) FROM $table WHERE card_id IN ($marks) GROUP BY card_id");
            $stmt->execute(array_values($cardIds));
            foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$cardId, $count]) {
                $counts[(int) $cardId] ??= ['comments' => 0, 'votes' => 0];
                $counts[(int) $cardId][$key] = (int) $count;
            }
        }

        return $counts;
    }

    /**
     * @param list<int> $commentIds
     * @return array<int, list<int>>
     */
    private function mentionsOf(array $commentIds): array
    {
        if ($commentIds === []) {
            return [];
        }

        $marks = implode(', ', array_fill(0, count($commentIds), '?'));
        $stmt = $this->db->prepare("SELECT comment_id, user_id FROM comment_mentions WHERE comment_id IN ($marks)");
        $stmt->execute($commentIds);

        $byComment = [];
        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$commentId, $userId]) {
            $byComment[(int) $commentId][] = (int) $userId;
        }

        return $byComment;
    }

    /** @param list<int> $userIds */
    private function saveMentions(int $commentId, array $userIds): void
    {
        $insert = $this->db->prepare('INSERT INTO comment_mentions (comment_id, user_id) VALUES (?, ?)');
        foreach (array_unique($userIds) as $userId) {
            $insert->execute([$commentId, $userId]);
        }
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
