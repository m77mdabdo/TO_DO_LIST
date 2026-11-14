<?php

declare(strict_types=1);

/*
 | Sends queued webhook messages, retrying failed ones later (after 1, 5,
 | 30, 120 and 720 minutes, then it gives up).
 |
 |   php bin/webhooks.php
 |
 | Schedule it with cron (or Windows Task Scheduler) every minute or two:
 |   php /path/to/todolist/bin/webhooks.php
 */

use App\Core\Config;
use App\Modules\Integration\Application\Webhooks;
use App\Session\ArraySession;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/autoload.php';

$app = (require __DIR__ . '/../bootstrap/app.php')(Config::load(dirname(__DIR__)), new ArraySession());
[$sent, $failed] = $app->get(Webhooks::class)->deliverDue();

echo "Sent $sent webhook message(s), $failed failed attempt(s).\n";
