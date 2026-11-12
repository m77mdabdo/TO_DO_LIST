<?php

declare(strict_types=1);

namespace App\Modules\Activity\Contract;

final class ActivityEntry
{
    /** @param array<string, string|int|null> $data */
    public function __construct(
        public readonly int $id,
        public readonly int $boardId,
        public readonly ?int $cardId,
        public readonly int $actorId,
        public readonly string $type,
        public readonly string $subject,
        public readonly array $data,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $data = json_decode((string) $row['data'], true);

        return new self(
            (int) $row['id'],
            (int) $row['board_id'],
            $row['card_id'] !== null ? (int) $row['card_id'] : null,
            (int) $row['actor_id'],
            (string) $row['type'],
            (string) $row['subject'],
            is_array($data) ? $data : [],
            (string) $row['created_at'],
        );
    }
}
