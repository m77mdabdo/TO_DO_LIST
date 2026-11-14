<?php

declare(strict_types=1);

/*
 | Deletes data that has no use any more:
 |  - password reset and email confirmation links that were used or expired a day ago,
 |  - failed login attempts past the 15-minute lockout,
 |  - request counters older than a day (the longest rate-limit window),
 |  - account security events older than SECURITY_LOG_DAYS (180 by default).
 |
 |   php bin/cleanup.php
 |
 | Schedule it once a day with cron (or Windows Task Scheduler):
 |   15 3 * * * php /path/to/todolist/bin/cleanup.php
 */

use App\Core\Config;
use App\Modules\Identity\Application\AccountCleanup;
use App\Security\RateLimiter;
use App\Session\ArraySession;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/autoload.php';

$config = Config::load(dirname(__DIR__));
$app = (require __DIR__ . '/../bootstrap/app.php')($config, new ArraySession());

$removed = $app->get(AccountCleanup::class)->run((int) $config->get('security.log_days', 180));
$removed['rate_limit_hits'] = $app->get(RateLimiter::class)->prune(86400);

foreach ($removed as $what => $count) {
    echo str_pad($what, 18) . $count . "\n";
}
