<?php
/**
 * @var App\View\ViewHelpers $v
 * @var App\View\View $view
 * @var string $appName
 * @var App\Auth\User $user
 * @var App\Task\Board $board
 * @var App\Task\BoardStats $stats
 * @var list<string> $categories
 */

use App\Task\Priority;
use App\Task\Status;
use App\Task\TaskRules;

$showDetails = $v->old('notes') !== '' || $v->old('category') !== '' || $v->old('due_date') !== '';
?>
<header class="topbar">
    <a class="brand" href="<?= $v->url('/') ?>">
        <span class="brand-mark"><?= $v->icon('check') ?></span>
        <span><?= $v->e($appName) ?></span>
    </a>
    <div class="topbar-actions">
        <button class="icon-btn js-theme" type="button" title="Toggle dark mode">
            <span class="show-light"><?= $v->icon('moon') ?></span><span class="show-dark"><?= $v->icon('sun') ?></span>
        </button>
        <span class="avatar" title="<?= $v->e($user->email) ?>"><?= $v->e($user->initial()) ?></span>
        <form action="<?= $v->url('/logout') ?>" method="post">
            <?= $v->csrfField() ?>
            <button class="icon-btn" title="Log out"><?= $v->icon('logout') ?></button>
        </form>
    </div>
</header>

<main class="wrap">

    <section class="hero">
        <div>
            <p class="eyebrow"><?= $v->e($v->today()->format('l, F j')) ?></p>
            <h1><?= $v->e($v->greeting()) ?>, <span class="gradient-text" dir="auto"><?= $v->e($user->firstName()) ?></span></h1>
            <p class="muted">
                <?php if ($stats->open === 0): ?>
                    Your board is clear. Add something you want to get done.
                <?php else: ?>
                    You have <strong><?= $stats->open ?></strong> open <?= $stats->open === 1 ? 'task' : 'tasks' ?><?= $stats->dueToday ? ', <strong>' . $stats->dueToday . '</strong> due today' : '' ?>.
                <?php endif; ?>
            </p>
        </div>

        <div class="progress-card">
            <div class="progress-top">
                <span>Progress</span>
                <strong class="js-percent"><?= $stats->percent() ?>%</strong>
            </div>
            <progress class="js-progress" max="100" value="<?= $stats->percent() ?>"><?= $stats->percent() ?>%</progress>
            <p class="muted small"><span class="js-done-count"><?= $stats->done ?></span> of <span class="js-total-count"><?= $stats->total ?></span> tasks finished</p>
        </div>
    </section>

    <section class="stats">
        <div class="stat stat-violet"><?= $v->icon('sparkle') ?><div><strong class="js-stat-open"><?= $stats->open ?></strong><span>Open</span></div></div>
        <div class="stat stat-amber"><?= $v->icon('calendar') ?><div><strong><?= $stats->dueToday ?></strong><span>Due today</span></div></div>
        <div class="stat stat-coral"><?= $v->icon('fire') ?><div><strong><?= $stats->overdue ?></strong><span>Overdue</span></div></div>
        <div class="stat stat-mint"><?= $v->icon('check') ?><div><strong class="js-stat-done-today"><?= $stats->doneToday ?></strong><span>Done today</span></div></div>
    </section>

    <section class="composer">
        <form action="<?= $v->url('/tasks') ?>" method="post" class="quick-add">
            <?= $v->csrfField() ?>
            <div class="quick-row">
                <span class="quick-icon"><?= $v->icon('plus') ?></span>
                <input id="quick-title" name="title" type="text" maxlength="<?= TaskRules::TITLE_MAX ?>" required autocomplete="off" dir="auto"
                       placeholder="What do you want to get done?" value="<?= $v->e($v->old('title')) ?>"
                       aria-invalid="<?= $v->invalid('title') ?>">
                <kbd class="hide-sm">N</kbd>
                <button class="btn btn-primary" type="submit"><span>Add</span><span class="hide-sm">task</span></button>
            </div>

            <details class="quick-more" <?= $showDetails ? 'open' : '' ?>>
                <summary>More details</summary>
                <div class="quick-grid">
                    <label>
                        <span>Priority</span>
                        <select name="priority">
                            <?php foreach (Priority::cases() as $priority): ?>
                                <option value="<?= $priority->value ?>" <?= $v->old('priority', Priority::Normal->value) === $priority->value ? 'selected' : '' ?>><?= $priority->label() ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Due date</span>
                        <input type="date" name="due_date" value="<?= $v->e($v->old('due_date')) ?>">
                    </label>
                    <label>
                        <span>List</span>
                        <input type="text" name="category" maxlength="<?= TaskRules::CATEGORY_MAX ?>" list="category-list" dir="auto"
                               placeholder="Work, Home, Study…" value="<?= $v->e($v->old('category')) ?>">
                    </label>
                    <label class="span-all">
                        <span>Notes</span>
                        <textarea name="notes" rows="2" maxlength="<?= TaskRules::NOTES_MAX ?>" dir="auto" placeholder="Anything to remember…"><?= $v->e($v->old('notes')) ?></textarea>
                    </label>
                </div>
            </details>
            <?= $view->partial('tasks/category_list', ['categories' => $categories]) ?>
        </form>
    </section>

    <section class="toolbar">
        <label class="search">
            <?= $v->icon('search') ?>
            <input id="search" type="search" placeholder="Search tasks…" autocomplete="off" dir="auto">
            <kbd class="hide-sm">/</kbd>
        </label>
        <div class="chips" role="group" aria-label="Filters">
            <button type="button" class="chip is-active" data-filter="all">All</button>
            <button type="button" class="chip" data-filter="today">Today</button>
            <button type="button" class="chip" data-filter="overdue">Overdue</button>
            <button type="button" class="chip" data-filter="high">High priority</button>
            <?php foreach ($categories as $category): ?>
                <button type="button" class="chip chip-cat <?= $v->categoryClass($category) ?>" data-filter="cat:<?= $v->e(mb_strtolower($category)) ?>" dir="auto"><?= $v->e($category) ?></button>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="board">
        <?php foreach (Status::cases() as $status): ?>
            <?php $tasks = $board->column($status); ?>
            <div class="column column-<?= $status->value ?>" data-status="<?= $status->value ?>">
                <header class="column-head">
                    <div>
                        <h2><?= $status->label() ?> <span class="count js-count"><?= count($tasks) ?></span></h2>
                        <p class="muted small"><?= $status->hint() ?></p>
                    </div>
                    <?php if ($status === Status::Done && $tasks): ?>
                        <form action="<?= $v->url('/board/clear-done') ?>" method="post" class="js-delete" data-confirm="Remove all finished tasks?">
                            <?= $v->csrfField() ?>
                            <button class="icon-btn" title="Clear finished tasks"><?= $v->icon('broom') ?></button>
                        </form>
                    <?php endif; ?>
                </header>

                <div class="column-body js-dropzone">
                    <?php foreach ($tasks as $task): ?>
                        <?= $view->partial('tasks/card', ['task' => $task]) ?>
                    <?php endforeach; ?>
                    <div class="empty">
                        <?= $v->icon($status === Status::Done ? 'sparkle' : 'plus') ?>
                        <span><?= $status === Status::Todo ? 'Nothing here yet' : 'Drop a task here' ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <p class="shortcuts muted small">
        <?= $v->icon('keyboard') ?> <kbd>N</kbd> new task · <kbd>/</kbd> search · <kbd>Esc</kbd> clear · drag cards between columns
    </p>
</main>
