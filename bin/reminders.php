<?php

declare(strict_types=1);

/*
 | Sends due-date reminders ("due soon" and "overdue"). Safe to run often:
 | each reminder goes out once per card and due date.
 |
 |   php bin/reminders.php
 |
 | Schedule it with cron (or Windows Task Scheduler) every 15 minutes:
 |   php /path/to/todolist/bin/reminders.php
 */

use App\Core\Config;
use App\Modules\Notification\Application\DueReminders;
use App\Session\ArraySession;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/autoload.php';

$app = (require __DIR__ . '/../bootstrap/app.php')(Config::load(dirname(__DIR__)), new ArraySession());
$sent = $app->get(DueReminders::class)->run();

echo "Sent $sent reminder(s).\n";
