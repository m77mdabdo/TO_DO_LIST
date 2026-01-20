<?php

require_once __DIR__ . '/App.php';

use Classes\Csrf;
use Classes\TaskRepository;

$user       = require_login();
$board      = $tasks->board($user['id']);
$stats      = $tasks->stats($board);
$categories = $tasks->categories($user['id']);
$firstName  = explode(' ', trim($user['name']))[0];

$columnHints = [
    'todo'  => 'Everything waiting for you',
    'doing' => 'What you are on right now',
    'done'  => 'Nice work, keep it going',
];

$pageTitle = 'Board';
$bodyClass = 'page-board';
require __DIR__ . '/inc/header.php';
?>

<header class="topbar">
    <a class="brand" href="<?= url('index.php') ?>">
        <span class="brand-mark"><?= icon('check') ?></span>
        <span><?= e($config['app_name']) ?></span>
    </a>
    <div class="topbar-actions">
        <button class="icon-btn js-theme" type="button" title="Toggle dark mode">
            <span class="show-light"><?= icon('moon') ?></span><span class="show-dark"><?= icon('sun') ?></span>
        </button>
        <span class="avatar" title="<?= e($user['email']) ?>"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
        <form action="<?= url('handle/logout.php') ?>" method="post">
            <?= Csrf::field() ?>
            <button class="icon-btn" title="Log out"><?= icon('logout') ?></button>
        </form>
    </div>
</header>

<main class="wrap">

    <section class="hero">
        <div>
            <p class="eyebrow"><?= e(date('l, F j')) ?></p>
            <h1><?= e(greeting()) ?>, <span class="gradient-text" dir="auto"><?= e($firstName) ?></span></h1>
            <p class="muted">
                <?php if ($stats['open'] === 0): ?>
                    Your board is clear. Add something you want to get done.
                <?php else: ?>
                    You have <strong><?= $stats['open'] ?></strong> open <?= $stats['open'] === 1 ? 'task' : 'tasks' ?><?= $stats['today'] ? ', <strong>' . $stats['today'] . '</strong> due today' : '' ?>.
                <?php endif; ?>
            </p>
        </div>

        <div class="progress-card">
            <div class="progress-top">
                <span>Progress</span>
                <strong class="js-percent"><?= $stats['percent'] ?>%</strong>
            </div>
            <progress class="js-progress" max="100" value="<?= $stats['percent'] ?>"><?= $stats['percent'] ?>%</progress>
            <p class="muted small"><span class="js-done-count"><?= $stats['done'] ?></span> of <span class="js-total-count"><?= $stats['total'] ?></span> tasks finished</p>
        </div>
    </section>

    <section class="stats">
        <div class="stat stat-violet"><?= icon('sparkle') ?><div><strong class="js-stat-open"><?= $stats['open'] ?></strong><span>Open</span></div></div>
        <div class="stat stat-amber"><?= icon('calendar') ?><div><strong><?= $stats['today'] ?></strong><span>Due today</span></div></div>
        <div class="stat stat-coral"><?= icon('fire') ?><div><strong><?= $stats['overdue'] ?></strong><span>Overdue</span></div></div>
        <div class="stat stat-mint"><?= icon('check') ?><div><strong class="js-stat-done-today"><?= $stats['done_today'] ?></strong><span>Done today</span></div></div>
    </section>

    <section class="composer">
        <form action="<?= url('handle/addToDo.php') ?>" method="post" class="quick-add">
            <?= Csrf::field() ?>
            <div class="quick-row">
                <span class="quick-icon"><?= icon('plus') ?></span>
                <input id="quick-title" name="title" type="text" maxlength="150" required autocomplete="off" dir="auto"
                       placeholder="What do you want to get done?" value="<?= e(old('title')) ?>"
                       aria-invalid="<?= isset(errors()['title']) ? 'true' : 'false' ?>">
                <kbd class="hide-sm">N</kbd>
                <button class="btn btn-primary" type="submit"><span>Add</span><span class="hide-sm">task</span></button>
            </div>

            <details class="quick-more" <?= old('notes') || old('category') || old('due_date') ? 'open' : '' ?>>
                <summary>More details</summary>
                <div class="quick-grid">
                    <label>
                        <span>Priority</span>
                        <select name="priority">
                            <?php foreach (TaskRepository::PRIORITIES as $value => $label): ?>
                                <option value="<?= $value ?>" <?= old('priority', 'normal') === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Due date</span>
                        <input type="date" name="due_date" value="<?= e(old('due_date')) ?>">
                    </label>
                    <label>
                        <span>List</span>
                        <input type="text" name="category" maxlength="30" list="category-list" dir="auto"
                               placeholder="Work, Home, Study…" value="<?= e(old('category')) ?>">
                    </label>
                    <label class="span-all">
                        <span>Notes</span>
                        <textarea name="notes" rows="2" maxlength="1000" dir="auto" placeholder="Anything to remember…"><?= e(old('notes')) ?></textarea>
                    </label>
                </div>
            </details>
            <datalist id="category-list">
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category) ?>">
                <?php endforeach; ?>
            </datalist>
        </form>
    </section>

    <section class="toolbar">
        <label class="search">
            <?= icon('search') ?>
            <input id="search" type="search" placeholder="Search tasks…" autocomplete="off" dir="auto">
            <kbd class="hide-sm">/</kbd>
        </label>
        <div class="chips" role="group" aria-label="Filters">
            <button type="button" class="chip is-active" data-filter="all">All</button>
            <button type="button" class="chip" data-filter="today">Today</button>
            <button type="button" class="chip" data-filter="overdue">Overdue</button>
            <button type="button" class="chip" data-filter="high">High priority</button>
            <?php foreach ($categories as $category): ?>
                <button type="button" class="chip chip-cat <?= category_class($category) ?>" data-filter="cat:<?= e(mb_strtolower($category)) ?>" dir="auto"><?= e($category) ?></button>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="board" data-move-url="<?= url('handle/goto.php') ?>">
        <?php foreach (TaskRepository::STATUSES as $status => $label): ?>
            <div class="column column-<?= $status ?>" data-status="<?= $status ?>">
                <header class="column-head">
                    <div>
                        <h2><?= e($label) ?> <span class="count js-count"><?= count($board[$status]) ?></span></h2>
                        <p class="muted small"><?= e($columnHints[$status]) ?></p>
                    </div>
                    <?php if ($status === 'done' && $board['done']): ?>
                        <form action="<?= url('handle/clearDone.php') ?>" method="post" class="js-delete" data-confirm="Remove all finished tasks?">
                            <?= Csrf::field() ?>
                            <button class="icon-btn" title="Clear finished tasks"><?= icon('broom') ?></button>
                        </form>
                    <?php endif; ?>
                </header>

                <div class="column-body js-dropzone">
                    <?php foreach ($board[$status] as $task): ?>
                        <?php require __DIR__ . '/inc/task_card.php'; ?>
                    <?php endforeach; ?>
                    <div class="empty">
                        <?= icon($status === 'done' ? 'sparkle' : 'plus') ?>
                        <span><?= $status === 'todo' ? 'Nothing here yet' : 'Drop a task here' ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <p class="shortcuts muted small">
        <?= icon('keyboard') ?> <kbd>N</kbd> new task · <kbd>/</kbd> search · <kbd>Esc</kbd> clear · drag cards between columns
    </p>
</main>

<?php require __DIR__ . '/inc/footer.php'; ?>
