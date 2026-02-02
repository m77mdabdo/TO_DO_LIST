<?php

declare(strict_types=1);

namespace App\Task;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A task's due day, described relative to "today".
 */
final class DueDate
{
    private function __construct(private DateTimeImmutable $date)
    {
    }

    public static function fromString(string $value): self
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("Invalid due date [$value].");
        }

        return new self($date);
    }

    public function toString(): string
    {
        return $this->date->format('Y-m-d');
    }

    /** Whole days from $today until the due date; negative when late. */
    public function daysFrom(DateTimeImmutable $today): int
    {
        return (int) $today->setTime(0, 0)->diff($this->date)->format('%r%a');
    }

    public function state(DateTimeImmutable $today): string
    {
        $days = $this->daysFrom($today);

        return match (true) {
            $days < 0   => 'overdue',
            $days === 0 => 'today',
            $days <= 3  => 'soon',
            default     => 'later',
        };
    }

    public function label(DateTimeImmutable $today): string
    {
        $days = $this->daysFrom($today);

        return match (true) {
            $days === 0  => 'Today',
            $days === 1  => 'Tomorrow',
            $days === -1 => 'Yesterday',
            $days < -1   => abs($days) . ' days late',
            $days <= 6   => $this->date->format('l'),
            default      => $this->date->format('M j'),
        };
    }
}
