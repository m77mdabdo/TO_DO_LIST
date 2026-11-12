<?php

declare(strict_types=1);

/*
 | Fills the app with a demo account and some sample tasks.
 |
 |   php database/seed.php
 |
 | Login: demo@todo.test / demo1234
 */

use App\Modules\Identity\Domain\PasswordHasher;
use App\Core\Config;
use App\Session\ArraySession;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/autoload.php';

$app = (require __DIR__ . '/../bootstrap/app.php')(Config::load(dirname(__DIR__)), new ArraySession());
$db  = $app->get(PDO::class);

$email = 'demo@todo.test';

$db->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
$db->prepare('INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, ?)')
    ->execute(['Mohamed Abdo', $email, (new PasswordHasher())->hash('demo1234'), date('Y-m-d H:i:s')]);
$userId = (int) $db->lastInsertId();

$day = fn (int $offset) => date('Y-m-d', strtotime("$offset days"));
$ago = fn (string $when) => date('Y-m-d H:i:s', strtotime($when));

$samples = [
    // title, notes, status, priority, category, due, created, completed
    ['Prepare client proposal', 'Pricing table, timeline and a short case study at the end.', 'doing', 'high', 'Work', $day(0), '-2 days', null],
    ['Fix login redirect bug', 'Happens only when the session expires on the edit page.', 'todo', 'high', 'Work', $day(-1), '-3 days', null],
    ['Gym — leg day', null, 'todo', 'normal', 'Health', $day(0), '-5 hours', null],
    ['Read 20 pages of Atomic Habits', null, 'todo', 'low', 'Personal', $day(2), '-1 day', null],
    ['Plan weekend trip', "Check hotel prices\nBook the train tickets", 'todo', 'normal', 'Personal', $day(5), '-6 hours', null],
    ['Write LinkedIn post about this project', 'Screenshots + 3 things I learned.', 'doing', 'normal', 'Content', $day(1), '-1 day', null],
    ['تجهيز عرض السعر للعميل', 'إضافة تفاصيل الدفع والمدة', 'todo', 'normal', 'Work', $day(3), '-4 hours', null],
    ['Pay internet bill', null, 'done', 'normal', 'Home', null, '-3 days', '-2 hours'],
    ['Refactor validation classes', 'Rules with params like min:3 and max:150.', 'done', 'high', 'Work', null, '-4 days', '-1 day'],
    ['Call mom', null, 'done', 'low', 'Personal', null, '-2 days', '-30 minutes'],
];

$stmt = $db->prepare(
    'INSERT INTO tasks (user_id, title, notes, status, priority, category, due_date, created_at, updated_at, completed_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

foreach ($samples as [$title, $notes, $status, $priority, $category, $due, $created, $completed]) {
    $stmt->execute([
        $userId, $title, $notes, $status, $priority, $category, $due,
        $ago($created), $ago($created), $completed ? $ago($completed) : null,
    ]);
}

echo 'Seeded ' . count($samples) . " tasks. Log in with $email / demo1234\n";
