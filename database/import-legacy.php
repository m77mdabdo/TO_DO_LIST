<?php

declare(strict_types=1);

/*
 | Imports every user's old tasks into boards and cards, then checks that
 | each old task has exactly one card. Safe to run again: imported tasks
 | are skipped. The old tasks table is never changed or deleted.
 |
 |   php database/import-legacy.php
 |
 | Back up the database first (copy database/todo.sqlite, or mysqldump).
 */

use App\Core\Config;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\LegacyImport\Application\LegacyImporter;
use App\Session\ArraySession;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/autoload.php';

$app = (require __DIR__ . '/../bootstrap/app.php')(Config::load(dirname(__DIR__)), new ArraySession());
$importer = $app->get(LegacyImporter::class);
$users = $app->get(UserDirectory::class);

$before = $importer->totals();
echo "Old tasks: {$before['tasks']}, already imported: {$before['imported']}\n";

$failed = 0;
foreach ($importer->usersWithPendingTasks() as $userId) {
    $report = $importer->importFor($userId, $users->find($userId)?->name ?? 'User ' . $userId);
    echo sprintf("  user %d: +%d, now %d of %d%s\n", $userId, $report->importedNow, $report->importedTotal, $report->tasksTotal, $report->isComplete() ? '' : '  <-- CHECK');

    foreach ($report->problems as $problem) {
        echo "    ! $problem\n";
        $failed++;
    }
}

$after = $importer->totals();
$ok = $after['tasks'] === $after['imported'] && $failed === 0;
echo ($ok ? 'OK' : 'INCOMPLETE') . " — {$after['imported']} of {$after['tasks']} old tasks have a card.\n";
exit($ok ? 0 : 1);
