<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Infrastructure;

use App\Modules\Discussion\Contract\Comment;
use App\Modules\Discussion\Domain\DiscussionRepository;
use App\Support\Clock;
use PDO;

final class PdoDiscussion implements DiscussionRepository
{
    private const COLUMNS = 'id, card_id, board_id, author_id, body, created_at, edited_at, attachment_id';

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

        $ids = array_map(static fn (array $row) => (int) $row['id'], $rows);
        $mentions = $this->mentionsOf($ids);
        $reactions = $this->reactionsOf($ids);

        return array_map(static fn (array $row) => Comment::fromRow($row, $mentions[(int) $row['id']] ?? [], $reactions[(int) $row['id']] ?? []), $rows);
    }

    public function findComment(int $id): ?Comment
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM comments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Comment::fromRow($row, $this->mentionsOf([$id])[$id] ?? [], $this->reactionsOf([$id])[$id] ?? []) : null;
    }

    public function addComment(int $cardId, int $boardId, int $authorId, string $body, array $mentions, ?int $attachmentId = null): int
    {
        $this->db->prepare('INSERT INTO comments (card_id, board_id, author_id, body, created_at, attachment_id) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$cardId, $boardId, $authorId, $body, $this->now(), $attachmentId]);
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
        $this->db->prepare('DELETE FROM comment_reactions WHERE comment_id = ?')->execute([$id]);
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

    public function toggleReaction(int $commentId, int $userId, string $emoji): bool
    {
        $delete = $this->db->prepare('DELETE FROM comment_reactions WHERE comment_id = ? AND user_id = ? AND emoji = ?');
        $delete->execute([$commentId, $userId, $emoji]);
        if ($delete->rowCount() > 0) {
            return false;
        }

        try {
            $this->db->prepare('INSERT INTO comment_reactions (comment_id, user_id, emoji, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$commentId, $userId, $emoji, $this->now()]);
        } catch (\PDOException) {
            // A double click raced us to the same row; the primary key kept it single.
        }

        return true;
    }

    public function touchTyping(int $cardId, int $userId, string $staleBefore): void
    {
        $this->db->prepare('DELETE FROM card_typing WHERE typed_at < ?')->execute([$staleBefore]);
        $update = $this->db->prepare('UPDATE card_typing SET typed_at = ? WHERE card_id = ? AND user_id = ?');
        $update->execute([$this->now(), $cardId, $userId]);
        if ($update->rowCount() === 0) {
            try {
                $this->db->prepare('INSERT INTO card_typing (card_id, user_id, typed_at) VALUES (?, ?, ?)')->execute([$cardId, $userId, $this->now()]);
            } catch (\PDOException) {
                // Another request from the same person inserted it first.
            }
        }
    }

    public function stopTyping(int $cardId, int $userId): void
    {
        $this->db->prepare('DELETE FROM card_typing WHERE card_id = ? AND user_id = ?')->execute([$cardId, $userId]);
    }

    public function typingOn(int $cardId, string $since): array
    {
        $stmt = $this->db->prepare('SELECT user_id FROM card_typing WHERE card_id = ? AND typed_at >= ? ORDER BY typed_at');
        $stmt->execute([$cardId, $since]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param list<int> $commentIds
     * @return array<int, array<string, list<int>>>
     */
    private function reactionsOf(array $commentIds): array
    {
        if ($commentIds === []) {
            return [];
        }

        $marks = implode(', ', array_fill(0, count($commentIds), '?'));
        $stmt = $this->db->prepare("SELECT comment_id, emoji, user_id FROM comment_reactions WHERE comment_id IN ($marks) ORDER BY created_at, user_id");
        $stmt->execute($commentIds);

        $byComment = [];
        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$commentId, $emoji, $userId]) {
            $byComment[(int) $commentId][(string) $emoji][] = (int) $userId;
        }

        return $byComment;
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
