<?php

declare(strict_types=1);

namespace App\Modules\Automation\Domain;

use DateTimeImmutable;

/**
 * When a scheduled automation is due. A schedule is "every day / week /
 * month at an hour"; the scheduler asks for the latest moment it should have
 * run, and runs it once for that moment (the run log remembers).
 */
final class Schedule
{
    public const EVERY = ['day', 'week', 'month'];

    /** @param array<string, string|int> $schedule ['every' => ..., 'day' => ..., 'hour' => ...] */
    public static function latestSlot(array $schedule, DateTimeImmutable $now): DateTimeImmutable
    {
        $hour = max(0, min(23, (int) ($schedule['hour'] ?? 8)));
        $day = (int) ($schedule['day'] ?? 1);
        $today = $now->setTime($hour, 0);

        switch ($schedule['every'] ?? 'day') {
            case 'week':
                // day: 0 = Sunday ... 6 = Saturday
                $back = ((int) $now->format('w') - max(0, min(6, $day)) + 7) % 7;
                $slot = $today->modify("-$back days");

                return $slot > $now ? $slot->modify('-7 days') : $slot;
            case 'month':
                // Day 1-28, so every month has it.
                $slot = $today->setDate((int) $now->format('Y'), (int) $now->format('n'), max(1, min(28, $day)));

                return $slot > $now ? $slot->modify('-1 month') : $slot;
            default:
                return $today > $now ? $today->modify('-1 day') : $today;
        }
    }

    /**
     * The date a due-date automation fires for a card due on $due:
     * N days before, on the day, or N days after.
     *
     * @param array<string, string|int> $schedule ['when' => before|on|after, 'days' => int]
     */
    public static function fireDate(array $schedule, string $due): string
    {
        $days = max(0, (int) ($schedule['days'] ?? 0));

        return match ($schedule['when'] ?? 'on') {
            'before' => (new DateTimeImmutable($due))->modify("-$days days")->format('Y-m-d'),
            'after'  => (new DateTimeImmutable($due))->modify("+$days days")->format('Y-m-d'),
            default  => $due,
        };
    }
}
