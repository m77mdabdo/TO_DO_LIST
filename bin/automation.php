<?php

declare(strict_types=1);

/*
 | Runs scheduled automations ("every Monday at 9:00") and due-date
 | automations ("one day before a card is due"). Safe to run often: each
 | schedule time and each card's due date runs once.
 |
 |   php bin/automation.php
 |
 | Schedule it with cron (or Windows Task Scheduler) every 15 minutes:
 |   php /path/to/todolist/bin/automation.php
 */

use App\Core\Config;
use App\Modules\Automation\Application\AutomationEngine;
use App\Session\ArraySession;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/autoload.php';

$app = (require __DIR__ . '/../bootstrap/app.php')(Config::load(dirname(__DIR__)), new ArraySession());
$ran = $app->get(AutomationEngine::class)->tick();

echo "Ran $ran automation(s).\n";
