<?php
/**
 * The board's cards on a month calendar, by due date (and start date), with
 * dated checklist items.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Access\Contract\BoardRole $role
 * @var \App\Support\MonthGrid $grid
 * @var array<string, list<array{card: \App\Modules\Card\Contract\CardSummary, kind: string}>> $byDay
 * @var array<int, \App\Modules\Card\Contract\CardSummary> $cardById
 * @var array<string, list<\App\Modules\Checklist\Contract\ChecklistItem>> $checklistItems added by the Checklist module
 * @var array<string, string> $query
 * @var \DateTimeImmutable $today
 */
$checklistItems ??= [];
$base = '/boards/' . $board->publicId . '/calendar';
$todayDate = $today->format('Y-m-d');
$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$monthLabel = $this->t($monthNames[(int) $grid->month->format('n') - 1]) . ' ' . $grid->month->format('Y');
$monthLink = fn (string $month): string => $this->url($base . '?' . http_build_query($query + ['month' => $month]));
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('boards/view-bar', ['board' => $board, 'role' => $role, 'current' => '/calendar', 'query' => $query]) ?>

<div class="board-tools">
    <?= $this->partial('boards/filters', compact('board', 'filter', 'boardLabels', 'audience', 'people', 'totalCards', 'shownCards', 'viewPath')) ?>
</div>

<main class="wrap view-page">
    <section class="card calendar">
        <header class="calendar-head">
            <a class="icon-btn" href="<?= $this->e($monthLink($grid->previous())) ?>" title="<?= $this->te('Previous month') ?>" aria-label="<?= $this->te('Previous month') ?>"><?= $this->icon($this->direction() === 'rtl' ? 'right' : 'left') ?></a>
            <h2><?= $this->e($monthLabel) ?></h2>
            <a class="icon-btn" href="<?= $this->e($monthLink($grid->next())) ?>" title="<?= $this->te('Next month') ?>" aria-label="<?= $this->te('Next month') ?>"><?= $this->icon($this->direction() === 'rtl' ? 'left' : 'right') ?></a>
            <a class="btn btn-ghost" href="<?= $this->e($monthLink($today->format('Y-m'))) ?>"><?= $this->te('Today') ?></a>
        </header>

        <div class="calendar-grid" role="grid" aria-label="<?= $this->e($monthLabel) ?>">
            <div class="calendar-row calendar-days" role="row">
                <?php foreach ($grid->weeks[0] as $date): ?>
                    <span role="columnheader"><?= $this->te($dayNames[(int) (new DateTimeImmutable($date))->format('w')]) ?></span>
                <?php endforeach; ?>
            </div>
            <?php foreach ($grid->weeks as $week): ?>
                <div class="calendar-row" role="row">
                    <?php foreach ($week as $date): ?>
                        <?php $entries = $byDay[$date] ?? []; $items = $checklistItems[$date] ?? []; ?>
                        <div role="gridcell" class="calendar-day <?= $grid->contains($date) ? '' : 'is-outside' ?> <?= $date === $todayDate ? 'is-today' : '' ?> <?= $entries || $items ? 'has-entries' : '' ?>">
                            <span class="calendar-date" data-day="<?= $this->e($date) ?>"><?= (int) substr($date, 8, 2) ?></span>
                            <?php foreach ($entries as ['card' => $card, 'kind' => $kind]): ?>
                                <a class="calendar-entry <?= $card->isComplete() ? 'is-done' : ($kind === 'due' && $card->isOverdue($today) ? 'is-late' : '') ?> <?= $kind === 'start' ? 'is-start' : '' ?>"
                                   href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"
                                   title="<?= $this->te($kind === 'start' ? 'Starts' : 'Due') ?>: <?= $this->e($card->title) ?>"><?php if ($kind === 'start'): ?><span aria-hidden="true">▸</span> <?php endif; ?><?= $this->e($card->title) ?></a>
                            <?php endforeach; ?>
                            <?php foreach ($items as $item): ?>
                                <?php $parent = $cardById[$item->cardId] ?? null; if ($parent === null) { continue; } ?>
                                <a class="calendar-entry is-item <?= $item->isDone() ? 'is-done' : '' ?>" href="<?= $this->url('/cards/' . $parent->publicId) ?>" dir="auto"
                                   title="<?= $this->e($parent->title) ?>"><?= $this->icon('checklist') ?><?= $this->e($item->text) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="muted small calendar-legend">
            <span class="calendar-entry"><?= $this->te('Due') ?></span>
            <span class="calendar-entry is-start">▸ <?= $this->te('Starts') ?></span>
            <span class="calendar-entry is-late"><?= $this->te('Overdue') ?></span>
            <span class="calendar-entry is-item"><?= $this->icon('checklist') ?><?= $this->te('Checklist item') ?></span>
        </p>
    </section>
</main>
