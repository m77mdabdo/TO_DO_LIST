<?php

declare(strict_types=1);

/*
 | Fills a fresh database with a demo company to click around in.
 |
 |   php database/seed.php
 |
 | Logins (password demo1234 for both):
 |   demo@todo.test  owner of "Nile Studio", plus a few old-style tasks that
 |                   are imported into "My Board" on the first login
 |   mona@todo.test  a member of "Nile Studio" who works on the team board
 |
 | Everything goes through the same services the app uses, so the data
 | passes the same validation and permission checks.
 */

use App\Core\Config;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Application\CardService;
use App\Modules\Checklist\Application\ChecklistService;
use App\Modules\Discussion\Application\DiscussionService;
use App\Modules\Identity\Application\AuthService;
use App\Modules\Label\Application\LabelService;
use App\Modules\Workspace\Application\WorkspaceService;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Modules\Workspace\Domain\WorkspaceRepository;
use App\Session\ArraySession;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/autoload.php';

$app = (require __DIR__ . '/../bootstrap/app.php')(Config::load(dirname(__DIR__)), new ArraySession());
$db = $app->get(PDO::class);

$exists = $db->prepare('SELECT 1 FROM users WHERE email = ?');
$exists->execute(['demo@todo.test']);
if ($exists->fetchColumn()) {
    echo "Already seeded. Delete database/todo.sqlite (or empty the MySQL database) to start over.\n";
    exit;
}

$auth = $app->get(AuthService::class);
$register = static function (string $name, string $email) use ($auth): int {
    $id = $auth->register(['name' => $name, 'email' => $email, 'password' => 'demo1234', 'password_confirmation' => 'demo1234'])->id;
    $auth->logout();

    return $id;
};

$demo = $register('Mohamed Abdo', 'demo@todo.test');
$mona = $register('Mona Hassan', 'mona@todo.test');

// A company workspace with a team board everyone in it can see.
$workspace = $app->get(WorkspaceService::class)->create($demo, ['company_name' => 'Nile Studio']);
$app->get(WorkspaceRepository::class)->addMember($workspace->id, $mona, WorkspaceRole::Member);
$app->get(WorkspaceService::class)->switchTo($mona, $workspace->id); // so Mona's first login opens it

$boards = $app->get(BoardService::class);
$cards = $app->get(CardService::class);
$labels = $app->get(LabelService::class);

$board = $boards->createBoard($demo, $workspace->id, 'Website launch', BoardVisibility::Workspace, 'sky');
// Mona works on this board (a board member, not just a viewer).
$app->get(BoardMembers::class)->add($board->id, $mona, BoardRole::Member);

$lists = [];
foreach (['Backlog', 'In progress', 'Review', 'Done'] as $name) {
    $lists[$name] = $boards->addList($demo, $board->id, $name)->id;
}

$label = static fn (string $name, string $color) => $labels->ensureLabel($demo, $board->id, $name, $color);
$design = $label('Design', 'purple');
$dev = $label('Development', 'blue');
$urgent = $label('Urgent', 'red');
$content = $label('Content', 'green');

$day = static fn (int $offset) => date('Y-m-d', strtotime("$offset days"));

$samples = [
    // list, title, description, due, labels, done
    ['Backlog', 'Write the About page', 'Team photos and a short story of the studio.', $day(6), [$content], false],
    ['Backlog', 'Pick the analytics tool', null, null, [$dev], false],
    ['Backlog', 'تجهيز محتوى الصفحة الرئيسية', 'نصوص قصيرة وواضحة باللغتين.', $day(4), [$content], false],
    ['In progress', 'Design the pricing page', 'Three plans, yearly toggle, FAQ at the bottom.', $day(1), [$design, $urgent], false],
    ['In progress', 'Build the contact form', 'Validation, spam protection and a thank-you page.', $day(2), [$dev], false],
    ['Review', 'Home page hero', null, $day(-1), [$design], false],
    ['Done', 'Set up the domain and HTTPS', null, null, [$dev], true],
    ['Done', 'Moodboard', null, null, [$design], true],
];

$created = [];
foreach ($samples as [$list, $title, $description, $due, $cardLabels, $done]) {
    $card = $created[$title] = $cards->createCard($demo, $board->id, $lists[$list], $title, ['description' => $description, 'due_date' => $due]);
    foreach ($cardLabels as $cardLabel) {
        $labels->attach($demo, $card->id, $cardLabel->id);
    }
    if ($done) {
        $cards->setComplete($demo, $card, true);
    }
}

// Card details: people, a start date, a checklist, a conversation and votes.
$pricing = $created['Design the pricing page'];
$cards->update($demo, $pricing, [
    'title'       => $pricing->title,
    'description' => "Three plans with a **yearly toggle** and an FAQ at the bottom.\n\n- Starter\n- Team\n- Business\n\nReference: https://example.com/pricing-ideas",
    'start_date'  => $day(-2),
    'due_date'    => $day(1),
]);
$cards->toggleMember($demo, $pricing, $demo);
$cards->toggleMember($demo, $pricing, $mona);
$cards->toggleMember($demo, $created['Build the contact form'], $mona);

$checklists = $app->get(ChecklistService::class);
$steps = $checklists->addChecklist($demo, $pricing, 'Before review');
foreach (['Write plan names', 'Pick prices', 'Draft the FAQ', 'Mobile layout'] as $i => $text) {
    $checklists->addItem($demo, $steps, $text);
}
foreach (array_slice($checklists->forCard($pricing->id)[0]->items, 0, 2) as $item) {
    $checklists->toggleItem($demo, $item->id);
}

$discussion = $app->get(DiscussionService::class);
$discussion->post($demo, $pricing, '@Mona can you check the yearly prices before Thursday?');
$discussion->post($mona, $pricing, 'Done. I think **Team** should be the highlighted plan.');
$discussion->toggleVote($demo, $pricing);
$discussion->toggleVote($mona, $pricing);
$discussion->toggleVote($mona, $created['Write the About page']);

// A private board only the owner sees (Mona is not a member).
$private = $boards->createBoard($demo, $workspace->id, 'Hiring', BoardVisibility::Private, 'coral');
$boards->addList($demo, $private->id, 'Candidates');

// Old-style tasks, as the app stored them before boards existed.
// They move to "My Board" the first time demo@todo.test logs in.
$ago = static fn (string $when) => date('Y-m-d H:i:s', strtotime($when));
$old = $db->prepare('INSERT INTO tasks (user_id, title, notes, status, priority, category, due_date, created_at, updated_at, completed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
foreach ([
    ['Prepare client proposal', 'Pricing table, timeline and a short case study.', 'doing', 'high', 'Work', $day(0), '-2 days', null],
    ['Read 20 pages', null, 'todo', 'low', 'Personal', $day(2), '-1 day', null],
    ['Pay internet bill', null, 'done', 'normal', 'Home', null, '-3 days', '-2 hours'],
] as [$title, $notes, $status, $priority, $category, $due, $created, $completed]) {
    $old->execute([$demo, $title, $notes, $status, $priority, $category, $due, $ago($created), $ago($created), $completed ? $ago($completed) : null]);
}

echo "Seeded \"Nile Studio\" with 2 boards. Log in with demo@todo.test / demo1234 (or mona@todo.test).\n";
