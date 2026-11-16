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

    /** How a due date repeats: completing the card moves its dates to the next time. */
    public const RECURRENCES = [
        'daily'    => 'Every day',
        'sun_thu'  => 'Every working day (Sunday to Thursday)',
        'mon_fri'  => 'Every working day (Monday to Friday)',
        'weekly'   => 'Every week',
        'monthly'  => 'Every month',
        'yearly'   => 'Every year',
    ];

    /** How often a scheduled copy of the card is made (see RecurringCards). */
    public const REPEAT_FREQUENCIES = ['daily' => 'Every day', 'weekly' => 'Every week, on the days below', 'monthly' => 'Every month, on the day below'];
    public const WEEKDAYS = ['0' => 'Sunday', '1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday'];

    /** Reminder choices: "" keeps the usual "due soon" and "overdue"; numbers are days before the due date. */
    public const REMINDERS = [
        ''     => 'The usual reminders (due soon and overdue)',
        '0'    => 'On the due date',
        '1'    => '1 day before',
        '2'    => '2 days before',
        '7'    => '1 week before',
        'none' => 'No reminders',
    ];

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
        public readonly ?string $recurrence = null,
        public readonly ?string $remind = null,
        public readonly int $remindHour = 9,
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
            isset($row['recurrence']) && $row['recurrence'] !== '' ? (string) $row['recurrence'] : null,
            isset($row['remind']) && $row['remind'] !== '' ? (string) $row['remind'] : null,
            (int) ($row['remind_hour'] ?? 9),
        );
    }

    /**
     * The next due date after $due for a repeating rule: one step on, then
     * more steps until it is after $today, so a late card does not come back
     * already overdue. Monthly and yearly keep to the last day of a short month.
     */
    public static function nextOccurrence(string $due, string $rule, string $today): string
    {
        $day = (int) substr($due, 8, 2);
        $step = static function (string $date) use ($rule, $day): string {
            $d = new DateTimeImmutable($date);
            switch ($rule) {
                case 'weekly':
                    return $d->modify('+7 days')->format('Y-m-d');
                case 'monthly':
                case 'yearly':
                    $first = $d->modify('first day of this month')->modify($rule === 'monthly' ? '+1 month' : '+1 year');
                    return $first->format('Y-m-') . str_pad((string) min($day, (int) $first->format('t')), 2, '0', STR_PAD_LEFT);
                case 'sun_thu':
                case 'mon_fri':
                    $off = $rule === 'sun_thu' ? ['5', '6'] : ['6', '0'];
                    do {
                        $d = $d->modify('+1 day');
                    } while (in_array($d->format('w'), $off, true));
                    return $d->format('Y-m-d');
                default:
                    return $d->modify('+1 day')->format('Y-m-d');
            }
        };

        $next = $step($due);
        for ($i = 0; $next <= $today && $i < 5000; $i++) {
            $next = $step($next);
        }

        return $next;
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
