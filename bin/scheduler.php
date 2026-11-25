<?php

declare(strict_types=1);

/*
 | Runs the recurring jobs (reminders, automation, webhooks, backup, cleanup)
 | at their times, so one process replaces the separate cron lines.
 |
 |   php bin/scheduler.php           keep running (the Docker "scheduler" service)
 |   php bin/scheduler.php --once    run what is due this minute, then exit
 |                                   (for cron: * * * * * php /path/to/bin/scheduler.php --once)
 |   php bin/scheduler.php --list    show the jobs and when they run
 |   php bin/scheduler.php --run backup   run one job now (e.g. a backup before an upgrade)
 |
 | Each job runs as its own `php` process with a time limit. A lock file per job
 | (storage/scheduler/<job>.lock) makes sure the same job never runs twice at once,
 | even with a second scheduler or a leftover cron line on the same storage.
 */

use App\Core\Config;
use App\Scheduling\Schedule;
use App\Scheduling\ScheduledJob;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/autoload.php';

$root = dirname(__DIR__);
$config = Config::load($root);
date_default_timezone_set((string) $config->get('timezone', 'UTC'));

$schedule = Schedule::default();
$lockDir = $root . '/storage/scheduler';
if (!is_dir($lockDir)) {
    mkdir($lockDir, 0775, true);
}

$log = static function (string $message): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] $message\n");
};

if (in_array('--list', $argv, true)) {
    foreach ($schedule->jobs as $job) {
        printf("%-12s %-16s php %s\n", $job->name, $job->describe(), implode(' ', $job->command));
    }
    exit(0);
}

/** @var array<string, array{job: ScheduledJob, process: resource, lock: resource, pipes: array<int, resource>, started: float}> $running */
$running = [];

$start = static function (ScheduledJob $job) use (&$running, $lockDir, $root, $log): void {
    if (isset($running[$job->name])) {
        $log("{$job->name}: still running from last time, skipped");

        return;
    }

    $lock = fopen("$lockDir/{$job->name}.lock", 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        $log("{$job->name}: running elsewhere, skipped");

        return;
    }

    $command = [PHP_BINARY, ...$job->command];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) {
        flock($lock, LOCK_UN);
        $log("{$job->name}: could not start");

        return;
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $running[$job->name] = ['job' => $job, 'process' => $process, 'lock' => $lock, 'pipes' => $pipes, 'started' => microtime(true)];
    $log("{$job->name}: started");
};

// Forwards a job's output, ends jobs past their time limit, and releases finished ones.
$reap = static function () use (&$running, $log): void {
    foreach ($running as $name => $run) {
        foreach ([1, 2] as $fd) {
            while (($line = fgets($run['pipes'][$fd])) !== false) {
                $log("$name: " . rtrim($line));
            }
        }

        $status = proc_get_status($run['process']);
        $elapsed = microtime(true) - $run['started'];

        if ($status['running'] && $elapsed > $run['job']->timeoutSeconds) {
            proc_terminate($run['process'], 15);
            $log(sprintf('%s: stopped after %d s (time limit)', $name, (int) $elapsed));
            continue;
        }

        if (!$status['running']) {
            foreach ($run['pipes'] as $pipe) {
                fclose($pipe);
            }
            proc_close($run['process']);
            flock($run['lock'], LOCK_UN);
            fclose($run['lock']);
            unset($running[$name]);
            $log(sprintf('%s: finished in %.1f s (exit %d)', $name, $elapsed, $status['exitcode']));
        }
    }
};

$runDue = static function (DateTimeImmutable $minute) use ($schedule, $start): void {
    foreach ($schedule->dueAt($minute) as $job) {
        $start($job);
    }
};

$only = ($i = array_search('--run', $argv, true)) !== false ? ($argv[$i + 1] ?? '') : null;
if ($only !== null) {
    $jobs = array_values(array_filter($schedule->jobs, static fn (ScheduledJob $job) => $job->name === $only));
    if ($jobs === []) {
        fwrite(STDERR, "No job named \"$only\". See --list.\n");
        exit(2);
    }
    $start($jobs[0]);
    while ($running !== []) {
        $reap();
        usleep(200_000);
    }
    exit(0);
}

if (in_array('--once', $argv, true)) {
    $runDue(new DateTimeImmutable('now'));
    while ($running !== []) {
        $reap();
        usleep(200_000);
    }
    exit(0);
}

$log(sprintf('scheduler started (%d jobs, time zone %s)', count($schedule->jobs), date_default_timezone_get()));
$nextMinute = (new DateTimeImmutable('now'))->setTime((int) date('G'), (int) date('i'))->modify('+1 minute');

while (true) {
    $now = new DateTimeImmutable('now');
    if ($now >= $nextMinute) {
        $runDue($nextMinute);
        $nextMinute = $nextMinute->modify('+1 minute');
        // After a long pause (the host slept), don't replay every missed minute.
        if ($now->getTimestamp() - $nextMinute->getTimestamp() > 120) {
            $nextMinute = $now->setTime((int) $now->format('G'), (int) $now->format('i'))->modify('+1 minute');
        }
    }

    $reap();
    usleep(500_000);
}
