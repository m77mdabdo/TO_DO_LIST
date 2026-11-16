<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;

final class ListSummary
{
    public function __construct(
        public readonly int $id,
        public readonly int $boardId,
        public readonly string $name,
        public readonly string $position,
        public readonly bool $archived,
        /** One of BoardSummary::COLORS, or null for a plain list. */
        public readonly ?string $color = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self((int) $row['id'], (int) $row['board_id'], (string) $row['name'], (string) $row['position'], $row['archived_at'] !== null, isset($row['color']) && $row['color'] !== '' ? (string) $row['color'] : null);
    }
}
