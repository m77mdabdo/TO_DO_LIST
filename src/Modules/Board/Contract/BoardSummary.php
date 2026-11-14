<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;

final class BoardSummary
{
    public const COLORS = ['violet', 'sky', 'mint', 'amber', 'coral', 'slate'];

    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $workspaceId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $color,
        public readonly BoardVisibility $visibility,
        public readonly bool $archived,
        public readonly Participation $commenting = Participation::Everyone,
        public readonly Participation $voting = Participation::Members,
        public readonly bool $isTemplate = false,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['public_id'],
            (int) $row['workspace_id'],
            (string) $row['name'],
            $row['description'] !== null ? (string) $row['description'] : null,
            (string) $row['color'],
            BoardVisibility::from((string) $row['visibility']),
            $row['archived_at'] !== null,
            Participation::tryFrom((string) ($row['commenting'] ?? '')) ?? Participation::Everyone,
            Participation::tryFrom((string) ($row['voting'] ?? '')) ?? Participation::Members,
            (bool) ($row['is_template'] ?? false),
        );
    }
}
