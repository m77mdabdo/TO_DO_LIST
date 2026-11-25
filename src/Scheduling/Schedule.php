<?php

declare(strict_types=1);

namespace App\Scheduling;

use DateTimeImmutable;

/** The app's recurring jobs, run by bin/scheduler.php (or cron calling it with --once). */
final class Schedule
{
    /** @param list<ScheduledJob> $jobs */
    public function __construct(public readonly array $jobs)
    {
    }

    public static function default(): self
    {
        return new self([
            ScheduledJob::every(2, 'webhooks', ['bin/webhooks.php'], 300),
            ScheduledJob::every(15, 'reminders', ['bin/reminders.php'], 600),
            ScheduledJob::every(15, 'automation', ['bin/automation.php'], 600),
            ScheduledJob::dailyAt('03:00', 'backup', ['bin/backup.php', '--with-files'], 3600),
            ScheduledJob::dailyAt('03:30', 'cleanup', ['bin/cleanup.php'], 1800),
        ]);
    }

    /** @return list<ScheduledJob> */
    public function dueAt(DateTimeImmutable $at): array
    {
        return array_values(array_filter($this->jobs, static fn (ScheduledJob $job) => $job->isDue($at)));
    }
}
