<?php

declare(strict_types=1);

/*
 | Waits up to DB_WAIT_SECONDS (default 60) for the database to answer, so the
 | app doesn't start, and fail, while MySQL is still booting.
 */

use App\Core\Config;
use App\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    exit;
}

require __DIR__ . '/../../bootstrap/autoload.php';

$db = (array) Config::load(dirname(__DIR__, 2))->get('db');
$deadline = time() + (int) (getenv('DB_WAIT_SECONDS') ?: 60);

do {
    try {
        ConnectionFactory::make($db)->query('SELECT 1');
        exit(0);
    } catch (Throwable $e) {
        $last = $e->getMessage();
        sleep(2);
    }
} while (time() < $deadline);

fwrite(STDERR, "The database did not answer in time: $last\n");
exit(1);
