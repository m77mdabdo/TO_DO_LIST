<?php

declare(strict_types=1);

namespace App\Scheduling;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One command and when it runs: every N minutes (aligned to the clock, like
 * cron's star-slash-N) or once a day at HH:MM in the app's time zone.
 */
final class ScheduledJob
{
    /** @param list<string> $command arguments after `php`, e.g. ['bin/reminders.php'] */
    private function __construct(
        public readonly string $name,
        public readonly array $command,
        public readonly int $timeoutSeconds,
        private readonly ?int $everyMinutes,
        private readonly ?string $dailyAt,
    ) {
    }

    /** @param list<string> $command */
    public static function every(int $minutes, string $name, array $command, int $timeoutSeconds = 600): self
    {
        if ($minutes < 1 || 1440 % $minutes !== 0) {
            throw new InvalidArgumentException("Every $minutes minutes doesn't divide a day evenly.");
        }

        return new self($name, $command, $timeoutSeconds, $minutes, null);
    }

    /** @param list<string> $command */
    public static function dailyAt(string $time, string $name, array $command, int $timeoutSeconds = 3600): self
    {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new InvalidArgumentException("\"$time\" is not an HH:MM time.");
        }

        return new self($name, $command, $timeoutSeconds, null, $time);
    }

    public function isDue(DateTimeImmutable $at): bool
    {
        if ($this->everyMinutes !== null) {
            $minuteOfDay = (int) $at->format('G') * 60 + (int) $at->format('i');

            return $minuteOfDay % $this->everyMinutes === 0;
        }

        return $at->format('H:i') === $this->dailyAt;
    }

    public function describe(): string
    {
        return $this->everyMinutes !== null ? "every {$this->everyMinutes} min" : "daily at {$this->dailyAt}";
    }
}
