<?php
/**
 * @var \App\View\View $this
 * @var \App\Auth\User $user
 * @var \App\Task\Board $board
 * @var \App\Task\BoardStats $stats
 * @var list<string> $categories
 * @var string $greeting
 * @var \DateTimeImmutable $today
 * @var string $appName
 */
use App\Task\Priority;
use App\Task\Status;
use App\Task\TaskRules;

$form = $this->form;
?>
<header class="topbar">
    <a class="brand" href="<?= $this->url('/') ?>">
        <span class="brand-mark"><?= $this->icon('check') ?></span>
        <span><?= $this->e($appName) ?></span>
    </a>
    <div class="topbar-actions">
        <button class="icon-btn js-theme" type="button" title="Toggle dark mode">
            <span class="show-light"><?= $this->icon('moon') ?></span><span class="show-dark"><?= $this->icon('sun') ?></span>
        </button>
        <a class="icon-btn" href="<?= $this->url('/tasks/export') ?>" title="Download tasks as CSV"><?= $this->icon('download') ?></a>
        <span class="avatar" title="<?= $this->e($user->email) ?>"><?= $this->e($user->initial()) ?></span>
        <form action="<?= $this->url('/logout') ?>" method="post">
            <?= $this->csrfField() ?>
            <button class="icon-btn" title="Log out"><?= $this->icon('logout') ?></button>
        </form>
    </div>
</header>

<main class="wrap">

    <section class="hero">
        <div>
            <p class="eyebrow"><?= $this->e($today->format('l, F j')) ?></p>
            <h1><?= $this->e($greeting) ?>, <span class="gradient-text" dir="auto"><?= $this->e($user->firstName()) ?></span></h1>
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
        <div class="stat stat-violet"><?= $this->icon('sparkle') ?><div><strong class="js-stat-open"><?= $stats->open ?></strong><span>Open</span></div></div>
        <div class="stat stat-amber"><?= $this->icon('calendar') ?><div><strong><?= $stats->dueToday ?></strong><span>Due today</span></div></div>
        <div class="stat stat-coral"><?= $this->icon('fire') ?><div><strong><?= $stats->overdue ?></strong><span>Overdue</span></div></div>
        <div class="stat stat-mint"><?= $this->icon('check') ?><div><strong class="js-stat-done-today"><?= $stats->doneToday ?></strong><span>Done today</span></div></div>
    </section>

    <section class="composer">
        <form action="<?= $this->url('/tasks') ?>" method="post" class="quick-add">
            <?= $this->csrfField() ?>
            <div class="quick-row">
                <span class="quick-icon"><?= $this->icon('plus') ?></span>
                <input id="quick-title" name="title" type="text" maxlength="<?= TaskRules::TITLE_MAX ?>" required autocomplete="off" dir="auto"
                       placeholder="What do you want to get done?" value="<?= $this->e($form->old('title')) ?>"
                       aria-invalid="<?= $form->error('title') ? 'true' : 'false' ?>">
                <kbd class="hide-sm">N</kbd>
                <button class="btn btn-primary" type="submit"><span>Add</span><span class="hide-sm">task</span></button>
            </div>

            <details class="quick-more" <?= $form->old('notes') || $form->old('category') || $form->old('due_date') ? 'open' : '' ?>>
                <summary>More details</summary>
                <div class="quick-grid">
                    <label>
                        <span>Priority</span>
                        <select name="priority">
                            <?php foreach (Priority::cases() as $priority): ?>
                                <option value="<?= $priority->value ?>" <?= $form->old('priority', 'normal') === $priority->value ? 'selected' : '' ?>><?= $priority->label() ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Due date</span>
                        <input type="date" name="due_date" value="<?= $this->e($form->old('due_date')) ?>">
                    </label>
                    <label>
                        <span>List</span>
                        <input type="text" name="category" maxlength="<?= TaskRules::CATEGORY_MAX ?>" list="category-list" dir="auto"
                               placeholder="Work, Home, Study…" value="<?= $this->e($form->old('category')) ?>">
                    </label>
                    <label class="span-all">
                        <span>Notes</span>
                        <textarea name="notes" rows="2" maxlength="<?= TaskRules::NOTES_MAX ?>" dir="auto" placeholder="Anything to remember…"><?= $this->e($form->old('notes')) ?></textarea>
                    </label>
                </div>
            </details>
            <?= $this->partial('partials/category-list', ['categories' => $categories]) ?>
        </form>
    </section>

    <section class="toolbar">
        <label class="search">
            <?= $this->icon('search') ?>
            <input id="search" type="search" placeholder="Search tasks…" autocomplete="off" dir="auto">
            <kbd class="hide-sm">/</kbd>
        </label>
        <div class="chips" role="group" aria-label="Filters">
            <button type="button" class="chip is-active" data-filter="all">All</button>
            <button type="button" class="chip" data-filter="today">Today</button>
            <button type="button" class="chip" data-filter="overdue">Overdue</button>
            <button type="button" class="chip" data-filter="high">High priority</button>
            <?php foreach ($categories as $category): ?>
                <button type="button" class="chip chip-cat <?= $this->categoryClass($category) ?>" data-filter="cat:<?= $this->e(mb_strtolower($category)) ?>" dir="auto"><?= $this->e($category) ?></button>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="board">
        <?php foreach (Status::cases() as $status): $tasks = $board->column($status); ?>
            <div class="column column-<?= $status->value ?>" data-status="<?= $status->value ?>">
                <header class="column-head">
                    <div>
                        <h2><?= $status->label() ?> <span class="count js-count"><?= count($tasks) ?></span></h2>
                        <p class="muted small"><?= $status->hint() ?></p>
                    </div>
                    <?php if ($status === Status::Done && $tasks): ?>
                        <form action="<?= $this->url('/tasks/clear-done') ?>" method="post" class="js-delete" data-confirm="Remove all finished tasks?">
                            <?= $this->csrfField() ?>
                            <button class="icon-btn" title="Clear finished tasks"><?= $this->icon('broom') ?></button>
                        </form>
                    <?php endif; ?>
                </header>

                <div class="column-body js-dropzone">
                    <?php foreach ($tasks as $task): ?>
                        <?= $this->partial('partials/task-card', ['task' => $task]) ?>
                    <?php endforeach; ?>
                    <div class="empty">
                        <?= $this->icon($status === Status::Done ? 'sparkle' : 'plus') ?>
                        <span><?= $status === Status::Todo ? 'Nothing here yet' : 'Drop a task here' ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <p class="shortcuts muted small">
        <?= $this->icon('keyboard') ?> <kbd>N</kbd> new task · <kbd>/</kbd> search · <kbd>Esc</kbd> clear · drag cards between columns
    </p>
</main>
