<?php

declare(strict_types=1);

namespace App\Modules\Label\Contract;

final class Label
{
    public const COLORS = ['green', 'yellow', 'orange', 'red', 'purple', 'blue', 'sky', 'pink', 'slate'];

    public function __construct(
        public readonly int $id,
        public readonly int $boardId,
        public readonly string $name,
        public readonly string $color,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self((int) $row['id'], (int) $row['board_id'], (string) $row['name'], (string) $row['color']);
    }
}
