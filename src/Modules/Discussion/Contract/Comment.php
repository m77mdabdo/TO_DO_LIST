<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Contract;

final class Comment
{
    /** @param list<int> $mentions user ids @mentioned in the body */
    public function __construct(
        public readonly int $id,
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $authorId,
        public readonly string $body,
        public readonly string $createdAt,
        public readonly ?string $editedAt,
        public readonly array $mentions = [],
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row, array $mentions = []): self
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
        );
    }
}
