<?php

declare(strict_types=1);

/*
 | Backs up the database, and with --with-files the uploaded attachments,
 | into storage/backups (or BACKUPS_PATH). Keeps the newest BACKUPS_KEEP (14)
 | of each, and removes any older than BACKUPS_KEEP_DAYS days (0 = no limit);
 | the newest one is always kept. --keep and --keep-days override the .env.
 |
 |   php bin/backup.php
 |   php bin/backup.php --with-files --keep=30 --keep-days=90
 |
 | Schedule it once a day with cron (or Windows Task Scheduler), e.g. at 3am:
 |   0 3 * * * php /path/to/todolist/bin/backup.php --with-files
 */

use App\Core\Config;
use App\Database\Backup;
use App\Session\ArraySession;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/autoload.php';

$options = getopt('', ['with-files', 'keep:', 'keep-days:']);

$config = Config::load(dirname(__DIR__));
$keep = max(1, (int) ($options['keep'] ?? $config->get('backups.keep', 14)));
$keepDays = max(0, (int) ($options['keep-days'] ?? $config->get('backups.keep_days', 0)));
$app = (require __DIR__ . '/../bootstrap/app.php')($config, new ArraySession());
$backup = new Backup($app->get(PDO::class), (string) $config->get('backups.path', dirname(__DIR__) . '/storage/backups'));
$stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(2));

try {
    echo 'Database: ', $backup->database($stamp), "\n";
    if (isset($options['with-files'])) {
        echo 'Attachments: ', $backup->files((string) $config->get('attachments.path', dirname(__DIR__) . '/storage/attachments'), $stamp) ?? '(none yet)', "\n";
    }
    foreach ($backup->prune($keep, $keepDays) as $old) {
        echo 'Removed old backup: ', $old, "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Backup failed: ' . $e->getMessage() . "\n");
    exit(1);
}
