<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Contract;

final class Comment
{
    /**
     * @param list<int> $mentions user ids @mentioned in the body
     * @param array<string, list<int>> $reactions reaction key => user ids, in the order they reacted
     */
    public function __construct(
        public readonly int $id,
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $authorId,
        public readonly string $body,
        public readonly string $createdAt,
        public readonly ?string $editedAt,
        public readonly array $mentions = [],
        public readonly ?int $attachmentId = null,
        public readonly array $reactions = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param list<int> $mentions
     * @param array<string, list<int>> $reactions
     */
    public static function fromRow(array $row, array $mentions = [], array $reactions = []): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['card_id'],
            (int) $row['board_id'],
            (int) $row['author_id'],
            (string) $row['body'],
            (string) $row['created_at'],
            $row['edited_at'] !== null ? (string) $row['edited_at'] : null,
            $mentions,
            isset($row['attachment_id']) ? (int) $row['attachment_id'] : null,
            $reactions,
        );
    }
}
