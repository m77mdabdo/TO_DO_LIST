<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract;

use DateTimeImmutable;

final class CardSummary
{
    public const COVER_COLORS = ['violet', 'sky', 'mint', 'amber', 'coral', 'slate'];
    public const COVER_SIZES = ['top', 'full'];

    /** key => [emoji, English name (the translation key)] */
    public const STICKERS = [
        'star'    => ['⭐', 'Star'],
        'fire'    => ['🔥', 'Hot'],
        'rocket'  => ['🚀', 'Launch'],
        'idea'    => ['💡', 'Idea'],
        'warning' => ['⚠️', 'Warning'],
        'check'   => ['✅', 'Approved'],
        'heart'   => ['❤️', 'Loved'],
        'pin'     => ['📌', 'Pinned'],
    ];

    /** card: an ordinary card; separator: a heading between cards; board: a link to another board. */
    public const KINDS = ['card', 'separator', 'board'];

    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $boardId,
        public readonly int $listId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $position,
        public readonly ?string $dueAt,
        public readonly ?string $completedAt,
        public readonly bool $archived,
        public readonly string $createdAt,
        public readonly ?string $startAt = null,
        /** A template card: kept on the board to make new cards from. */
        public readonly bool $isTemplate = false,
        public readonly ?string $coverColor = null,
        /** One of the card's own images; cleared when that attachment is removed. */
        public readonly ?int $coverAttachmentId = null,
        public readonly string $coverSize = 'top',
        public readonly ?string $sticker = null,
        public readonly string $kind = 'card',
        public readonly ?int $linkBoardId = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['public_id'],
            (int) $row['board_id'],
            (int) $row['list_id'],
            (string) $row['title'],
            $row['description'] !== null ? (string) $row['description'] : null,
            (string) $row['position'],
            $row['due_at'] !== null ? (string) $row['due_at'] : null,
            $row['completed_at'] !== null ? (string) $row['completed_at'] : null,
            $row['archived_at'] !== null,
            (string) $row['created_at'],
            isset($row['start_at']) ? (string) $row['start_at'] : null,
            !empty($row['is_template']),
            isset($row['cover_color']) ? (string) $row['cover_color'] : null,
            isset($row['cover_attachment_id']) ? (int) $row['cover_attachment_id'] : null,
            (string) ($row['cover_size'] ?? 'top'),
            isset($row['sticker']) ? (string) $row['sticker'] : null,
            (string) ($row['kind'] ?? 'card'),
            isset($row['link_board_id']) ? (int) $row['link_board_id'] : null,
        );
    }

    public function hasCover(): bool
    {
        return $this->coverColor !== null || $this->coverAttachmentId !== null;
    }

    public function isSeparator(): bool
    {
        return $this->kind === 'separator';
    }

    public function isComplete(): bool
    {
        return $this->completedAt !== null;
    }

    /** Due date as Y-m-d, for date inputs and badges. */
    public function dueDate(): ?string
    {
        return $this->dueAt !== null ? substr($this->dueAt, 0, 10) : null;
    }

    /** Start date as Y-m-d. */
    public function startDate(): ?string
    {
        return $this->startAt !== null ? substr($this->startAt, 0, 10) : null;
    }

    public function isOverdue(DateTimeImmutable $today): bool
    {
        return !$this->isComplete() && $this->dueAt !== null && $this->dueDate() < $today->format('Y-m-d');
    }
}
