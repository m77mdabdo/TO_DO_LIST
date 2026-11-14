<?php
/**
 * Numbers about one board: progress, what is late, and how work is spread
 * over lists, people and labels.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Access\Contract\BoardRole $role
 * @var \App\Modules\Board\Contract\BoardStats $stats
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var array<string, string> $query
 */
$base = '/boards/' . $board->publicId;
// Widths come as classes in steps of 5% (w-0 … w-100): the CSP allows no inline styles.
$bar = static fn (int $value, int $max): string => (string) ($value <= 0 || $max <= 0 ? 0 : max(5, 5 * (int) round($value * 20 / $max)));
$listMax = max([1, ...array_map(static fn ($l) => $l['open'] + $l['done'], $stats->perList)]);
$personMax = max([1, ...array_map(static fn ($p) => $p['open'] + $p['done'], $stats->perPerson)]);
$labelMax = max([1, ...array_column($stats->perLabel, 'count')]);
$trendMax = max([1, ...array_values($stats->doneByDay)]);
$tiles = [
    ['Open cards', $stats->open, '', ''],
    ['Done', $stats->done, 'tile-good', '?status=done'],
    ['Overdue', $stats->overdue, $stats->overdue > 0 ? 'tile-bad' : '', '?due=overdue&status=open'],
    ['Due today', $stats->dueToday, $stats->dueToday > 0 ? 'tile-warn' : '', '?due=today&status=open'],
    ['Due in the next 7 days', $stats->dueSoon, '', '?due=week&status=open'],
    ['No due date', $stats->noDueDate, '', '?due=none&status=open'],
    ['Nobody assigned', $stats->unassigned, '', '?member=nobody&status=open'],
];
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('boards/view-bar', ['board' => $board, 'role' => $role, 'current' => '/dashboard', 'query' => []]) ?>

<main class="wrap view-page stack">
    <section class="card stack">
        <div class="progress-head">
            <h2 class="section-title"><?= $this->icon('chart') ?> <?= $this->te('Progress') ?></h2>
            <strong class="big-number"><?= $stats->percentDone() ?>%</strong>
        </div>
        <progress class="checklist-progress" max="100" value="<?= $stats->percentDone() ?>" aria-label="<?= $this->te('Cards done') ?>"></progress>
        <p class="muted small"><?= $this->te(':done of :total cards are done.', ['done' => (string) $stats->done, 'total' => (string) $stats->total]) ?></p>
    </section>

    <section class="stat-tiles" aria-label="<?= $this->te('Summary') ?>">
        <?php foreach ($tiles as [$label, $value, $class, $filter]): ?>
            <a class="stat-tile <?= $class ?>" href="<?= $this->e($this->url($base . $filter)) ?>">
                <strong><?= $value ?></strong>
                <span><?= $this->te($label) ?></span>
            </a>
        <?php endforeach; ?>
    </section>

    <div class="dashboard-grid">
        <section class="card stack">
            <h2 class="section-title"><?= $this->te('Cards per list') ?></h2>
            <?php if (!$stats->perList): ?><p class="muted small"><?= $this->te('No lists yet.') ?></p><?php endif; ?>
            <ul class="bars">
                <?php foreach ($stats->perList as $list): ?>
                    <li>
                        <span class="bar-label" dir="auto"><?= $this->e($list['name']) ?></span>
                        <span class="bar-track" aria-hidden="true">
                            <span class="bar bar-open" data-w="<?= $bar($list['open'], $listMax) ?>"></span><span class="bar bar-done" data-w="<?= $bar($list['done'], $listMax) ?>"></span>
                        </span>
                        <span class="bar-value"><?= $this->te(':open open · :done done', ['open' => (string) $list['open'], 'done' => (string) $list['done']]) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card stack">
            <h2 class="section-title"><?= $this->te('Work per person') ?></h2>
            <?php if (!$stats->perPerson): ?><p class="muted small"><?= $this->te('Nobody is assigned to a card yet.') ?></p><?php endif; ?>
            <ul class="bars">
                <?php foreach ($stats->perPerson as $userId => $counts): ?>
                    <li>
                        <span class="bar-label"><bdi><?= $this->e($people[$userId]->name ?? $this->t('Someone')) ?></bdi></span>
                        <span class="bar-track" aria-hidden="true">
                            <span class="bar bar-open" data-w="<?= $bar($counts['open'], $personMax) ?>"></span><span class="bar bar-done" data-w="<?= $bar($counts['done'], $personMax) ?>"></span>
                        </span>
                        <span class="bar-value"><?= $this->te(':open open · :done done', ['open' => (string) $counts['open'], 'done' => (string) $counts['done']]) ?><?php if ($counts['overdue'] > 0): ?> · <span class="danger"><?= $this->te(':count overdue', ['count' => (string) $counts['overdue']]) ?></span><?php endif; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card stack">
            <h2 class="section-title"><?= $this->te('Labels') ?></h2>
            <?php if (!$stats->perLabel): ?><p class="muted small"><?= $this->te('No card has a label yet.') ?></p><?php endif; ?>
            <ul class="bars">
                <?php foreach ($stats->perLabel as ['label' => $label, 'count' => $count]): ?>
                    <li>
                        <span class="bar-label"><span class="label label-<?= $this->e($label->color) ?>" dir="auto"><?= $this->e($label->name) ?></span></span>
                        <span class="bar-track" aria-hidden="true"><span class="bar bar-open" data-w="<?= $bar($count, $labelMax) ?>"></span></span>
                        <span class="bar-value"><?= $count ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card stack">
            <h2 class="section-title"><?= $this->te('Completed in the last 14 days') ?></h2>
            <div class="trend" role="img" aria-label="<?= $this->te(':count cards completed in the last 14 days', ['count' => (string) array_sum($stats->doneByDay)]) ?>">
                <?php foreach ($stats->doneByDay as $day => $count): ?>
                    <span class="trend-col" title="<?= $this->e($day) ?>: <?= $count ?>"><span class="trend-bar" data-h="<?= $bar($count, $trendMax) ?>"></span><small><?= (int) substr($day, 8, 2) ?></small></span>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <p class="muted small"><span class="legend legend-open"></span> <?= $this->te('Still open') ?> <span class="legend legend-done"></span> <?= $this->te('Done') ?></p>
</main>
