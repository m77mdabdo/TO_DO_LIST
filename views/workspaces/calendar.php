<?php
/**
 * Due and start dates from every board of the workspace I can see, on one
 * month calendar. Each entry carries its board's color and name.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var array<int, \App\Modules\Board\Contract\BoardSummary> $boards
 * @var string $chosen a board's public id, or ""
 * @var \App\Support\MonthGrid $grid
 * @var array<string, list<array{card: \App\Modules\Card\Contract\CardSummary, kind: string}>> $byDay
 * @var int $count
 * @var \DateTimeImmutable $today
 */
$base = '/workspaces/' . $workspace->publicId . '/calendar';
$todayDate = $today->format('Y-m-d');
$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$monthLabel = $this->t($monthNames[(int) $grid->month->format('n') - 1]) . ' ' . $grid->month->format('Y');
$monthLink = fn (string $month): string => $this->url($base . '?' . http_build_query(array_filter(['board' => $chosen, 'month' => $month])));
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('workspaces/views-nav', ['current' => 'calendar', 'workspace' => $workspace]) ?>

<div class="board-tools">
    <form action="<?= $this->url($base) ?>" method="get" class="filter-form card">
        <input type="hidden" name="month" value="<?= $this->e($grid->month->format('Y-m')) ?>">
        <label><span class="visually-hidden"><?= $this->te('Board') ?></span>
            <select name="board">
                <option value=""><?= $this->te('Every board') ?></option>
                <?php foreach ($boards as $board): ?>
                    <option value="<?= $this->e($board->publicId) ?>" <?= $chosen === $board->publicId ? 'selected' : '' ?>><?= $this->e($board->name) ?></option>
                <?php endforeach; ?>
            </select></label>
        <button class="btn btn-ghost" type="submit"><?= $this->icon('filter') ?><?= $this->te('Show') ?></button>
        <span class="muted small"><?= $this->te('Only boards you can open are included.') ?></span>
    </form>
</div>

<main class="wrap view-page">
    <section class="card calendar">
        <header class="calendar-head">
            <a class="icon-btn" href="<?= $this->e($monthLink($grid->previous())) ?>" title="<?= $this->te('Previous month') ?>" aria-label="<?= $this->te('Previous month') ?>"><?= $this->icon($this->direction() === 'rtl' ? 'right' : 'left') ?></a>
            <h2><?= $this->e($monthLabel) ?></h2>
            <a class="icon-btn" href="<?= $this->e($monthLink($grid->next())) ?>" title="<?= $this->te('Next month') ?>" aria-label="<?= $this->te('Next month') ?>"><?= $this->icon($this->direction() === 'rtl' ? 'left' : 'right') ?></a>
            <a class="btn btn-ghost" href="<?= $this->e($monthLink($today->format('Y-m'))) ?>"><?= $this->te('Today') ?></a>
        </header>
        <?php if ($count === 0): ?><p class="muted small center"><?= $this->te('No card on these boards starts or is due this month.') ?></p><?php endif; ?>

        <div class="calendar-grid" role="grid" aria-label="<?= $this->e($monthLabel) ?>">
            <div class="calendar-row calendar-days" role="row">
                <?php foreach ($grid->weeks[0] as $date): ?>
                    <span role="columnheader"><?= $this->te($dayNames[(int) (new DateTimeImmutable($date))->format('w')]) ?></span>
                <?php endforeach; ?>
            </div>
            <?php foreach ($grid->weeks as $week): ?>
                <div class="calendar-row" role="row">
                    <?php foreach ($week as $date): ?>
                        <?php $entries = $byDay[$date] ?? []; ?>
                        <div role="gridcell" class="calendar-day <?= $grid->contains($date) ? '' : 'is-outside' ?> <?= $date === $todayDate ? 'is-today' : '' ?> <?= $entries ? 'has-entries' : '' ?>">
                            <span class="calendar-date"><?= (int) substr($date, 8, 2) ?></span>
                            <?php foreach ($entries as ['card' => $card, 'kind' => $kind]): ?>
                                <?php $board = $boards[$card->boardId] ?? null; ?>
                                <a class="calendar-entry board-dot-<?= $this->e($board->color ?? 'violet') ?> <?= $card->isComplete() ? 'is-done' : ($kind === 'due' && $card->isOverdue($today) ? 'is-late' : '') ?> <?= $kind === 'start' ? 'is-start' : '' ?>"
                                   href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"
                                   title="<?= $this->e(($board->name ?? '') . ' · ' . $this->t($kind === 'start' ? 'Starts' : 'Due') . ': ' . $card->title) ?>"><?php if ($kind === 'start'): ?><span aria-hidden="true">▸</span> <?php endif; ?><?= $this->e($card->title) ?><span class="visually-hidden"> (<?= $this->e($board->name ?? '') ?>)</span></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="muted small calendar-legend">
            <?php foreach ($boards as $board): ?>
                <span class="calendar-entry board-dot-<?= $this->e($board->color) ?>" dir="auto"><?= $this->e($board->name) ?></span>
            <?php endforeach; ?>
            <span class="calendar-entry is-start">▸ <?= $this->te('Starts') ?></span>
            <span class="calendar-entry is-late"><?= $this->te('Overdue') ?></span>
        </p>
    </section>
</main>
