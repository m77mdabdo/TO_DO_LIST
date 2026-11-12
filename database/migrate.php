<?php

declare(strict_types=1);

/*
 | Applies pending migrations from every module.
 |
 |   php database/migrate.php            run what's pending
 |   php database/migrate.php --pending  only list it
 */

use App\Core\Config;
use App\Database\Migrations\MigrationRunner;
use App\Session\ArraySession;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/autoload.php';

$app = (require __DIR__ . '/../bootstrap/app.php')(Config::load(dirname(__DIR__)), new ArraySession(), '', false);
$runner = $app->get(MigrationRunner::class);

if (in_array('--pending', $argv, true)) {
    $pending = $runner->pending();
    echo $pending ? implode(PHP_EOL, $pending) . PHP_EOL : "Nothing pending.\n";
    exit;
}

$ran = $runner->migrate();
echo $ran ? 'Applied: ' . PHP_EOL . '  ' . implode(PHP_EOL . '  ', $ran) . PHP_EOL : "Nothing to migrate.\n";
