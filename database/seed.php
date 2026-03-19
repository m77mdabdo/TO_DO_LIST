<?php

declare(strict_types=1);

/*
 | Fills the app with a demo account and some sample tasks.
 |
 |   php database/seed.php
 |
 | Login: demo@todo.test / demo1234
 */

use App\Auth\UserRepository;
use App\Support\Clock;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$container = require __DIR__ . '/../bootstrap/app.php';

$db    = $container->get(PDO::class);
$users = $container->get(UserRepository::class);
$now   = $container->get(Clock::class)->now();
$email = 'demo@todo.test';

$db->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
$user = $users->create('Mohamed Abdo', $email, password_hash('demo1234', PASSWORD_DEFAULT));

$day = fn (int $offset) => $now->modify("$offset days")->format('Y-m-d');
$ago = fn (string $when) => $now->modify($when)->format('Y-m-d H:i:s');

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

// raw SQL on purpose: the repository always stamps "now", the demo needs dates in the past
$stmt = $db->prepare(
    'INSERT INTO tasks (user_id, title, notes, status, priority, category, due_date, created_at, updated_at, completed_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

foreach ($samples as [$title, $notes, $status, $priority, $category, $due, $created, $completed]) {
    $stmt->execute([
        $user->id, $title, $notes, $status, $priority, $category, $due,
        $ago($created), $ago($created), $completed ? $ago($completed) : null,
    ]);
}

echo 'Seeded ' . count($samples) . " tasks. Log in with $email / demo1234\n";
