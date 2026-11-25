<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Scheduling\Schedule;
use App\Scheduling\ScheduledJob;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\TestCase;

final class ScheduleTest extends TestCase
{
    public function testEveryNMinutesIsAlignedToTheClockLikeCron(): void
    {
        $job = ScheduledJob::every(15, 'reminders', ['bin/reminders.php']);

        $this->assertTrue($job->isDue(new DateTimeImmutable('2026-01-20 00:00')));
        $this->assertTrue($job->isDue(new DateTimeImmutable('2026-01-20 10:45')));
        $this->assertFalse($job->isDue(new DateTimeImmutable('2026-01-20 10:46')));
        $this->assertFalse($job->isDue(new DateTimeImmutable('2026-01-20 10:14')));
    }

    public function testDailyJobsRunOnlyAtTheirMinute(): void
    {
        $job = ScheduledJob::dailyAt('03:00', 'backup', ['bin/backup.php']);

        $this->assertTrue($job->isDue(new DateTimeImmutable('2026-01-20 03:00')));
        $this->assertFalse($job->isDue(new DateTimeImmutable('2026-01-20 03:01')));
        $this->assertFalse($job->isDue(new DateTimeImmutable('2026-01-20 15:00')));
    }

    public function testRefusesIntervalsAndTimesThatCanNotWork(): void
    {
        $this->assertThrows(InvalidArgumentException::class, fn () => ScheduledJob::every(7, 'x', ['x.php']));
        $this->assertThrows(InvalidArgumentException::class, fn () => ScheduledJob::every(0, 'x', ['x.php']));
        $this->assertThrows(InvalidArgumentException::class, fn () => ScheduledJob::dailyAt('24:00', 'x', ['x.php']));
        $this->assertThrows(InvalidArgumentException::class, fn () => ScheduledJob::dailyAt('3:00', 'x', ['x.php']));
    }

    public function testTheDefaultScheduleRunsEveryJobTheReadmeLists(): void
    {
        $names = array_map(static fn (ScheduledJob $job) => $job->name, Schedule::default()->jobs);
        sort($names);
        $this->assertSame(['automation', 'backup', 'cleanup', 'reminders', 'webhooks'], $names);

        foreach (Schedule::default()->jobs as $job) {
            $this->assertTrue(is_file(BASE_PATH . '/' . $job->command[0]), "{$job->name} points at a missing script");
        }
    }

    public function testWhatIsDueAtAGivenMinute(): void
    {
        $due = static fn (string $at) => array_map(
            static fn (ScheduledJob $job) => $job->name,
            Schedule::default()->dueAt(new DateTimeImmutable($at)),
        );

        $this->assertSame(['webhooks', 'reminders', 'automation', 'backup'], $due('2026-01-20 03:00'));
        $this->assertSame(['webhooks', 'reminders', 'automation', 'cleanup'], $due('2026-01-20 03:30'));
        $this->assertSame(['webhooks'], $due('2026-01-20 10:02'));
        $this->assertSame([], $due('2026-01-20 10:01'));
    }
}
