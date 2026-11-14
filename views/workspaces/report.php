<?php
/**
 * The team report: where work stands across the boards I can see, and what
 * happened over a period. Numbers to follow up on, not scores.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var \App\Modules\Board\Contract\TeamReport $report
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var int $days
 * @var list<int> $periods
 */
use App\Modules\Board\Contract\TeamReport;

$base = '/workspaces/' . $workspace->publicId;
$bar = static fn (int $value, int $max): string => (string) ($value <= 0 || $max <= 0 ? 0 : max(5, 5 * (int) round($value * 20 / $max)));
$personMax = max([1, ...array_map(static fn ($p) => $p['open'] + $p['completed'], $report->perPerson)]);
$weekMax = max([1, ...array_values($report->completedByWeek)]);
$t = $report->totals;
$tiles = [
    ['Open cards', $t['open'], '', '?status=open'],
    ['Overdue', $t['overdue'], $t['overdue'] > 0 ? 'tile-bad' : '', '?due=overdue&status=open'],
    ['Due in the next 7 days', $t['dueSoon'], '', '?due=week&status=open'],
    ['No due date', $t['noDueDate'], '', '?due=none&status=open'],
    ['Nobody assigned', $t['unassigned'], '', '?member=none&status=open'],
    ['Created in the period', $t['created'], '', ''],
    ['Completed in the period', $t['completed'], 'tile-good', ''],
];
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('workspaces/views-nav', ['current' => 'report', 'workspace' => $workspace]) ?>

<main class="wrap view-page stack report-page">
    <section class="card report-head">
        <p class="muted"><?= $this->te('From :from to :to, on the boards you can see. These numbers help a team follow up on work; they are not a measure of anyone\'s performance.', ['from' => $report->from, 'to' => $report->to]) ?></p>
        <nav class="view-tabs" aria-label="<?= $this->te('Period') ?>">
            <?php foreach ($periods as $period): ?>
                <a href="<?= $this->url($base . '/report?days=' . $period) ?>" class="<?= $period === $days ? 'is-active' : '' ?>" <?= $period === $days ? 'aria-current="page"' : '' ?>><?= $this->te('Last :days days', ['days' => (string) $period]) ?></a>
            <?php endforeach; ?>
        </nav>
        <a class="btn btn-ghost" href="<?= $this->url($base . '/report.csv?days=' . $days) ?>"><?= $this->icon('download') ?> <?= $this->te('Download (CSV)') ?></a>
    </section>

    <section class="stat-tiles" aria-label="<?= $this->te('Summary') ?>">
        <?php foreach ($tiles as [$label, $value, $class, $filter]): ?>
            <?php if ($filter !== ''): ?>
                <a class="stat-tile <?= $class ?>" href="<?= $this->e($this->url($base . '/cards' . $filter)) ?>"><strong><?= $value ?></strong><span><?= $this->te($label) ?></span></a>
            <?php else: ?>
                <div class="stat-tile <?= $class ?>"><strong><?= $value ?></strong><span><?= $this->te($label) ?></span></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>

    <div class="dashboard-grid">
        <section class="card stack">
            <h2 class="section-title"><?= $this->te('Work per person') ?></h2>
            <?php if ($report->perPerson === []): ?><p class="muted small"><?= $this->te('Nobody is assigned to a card yet.') ?></p><?php endif; ?>
            <ul class="bars">
                <?php foreach ($report->perPerson as $userId => $row): ?>
                    <li>
                        <span class="bar-label"><bdi><?= $this->e($people[$userId]->name ?? $this->t('Someone')) ?></bdi></span>
                        <span class="bar-track" aria-hidden="true">
                            <span class="bar bar-open" data-w="<?= $bar($row['open'], $personMax) ?>"></span><span class="bar bar-done" data-w="<?= $bar($row['completed'], $personMax) ?>"></span>
                        </span>
                        <span class="bar-value"><?= $this->te(':open open · :done completed', ['open' => (string) $row['open'], 'done' => (string) $row['completed']]) ?><?php if ($row['overdue'] > 0): ?> · <span class="danger"><?= $this->te(':count overdue', ['count' => (string) $row['overdue']]) ?></span><?php endif; ?> · <?= $this->te(':count updates', ['count' => (string) $row['actions']]) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card stack">
            <h2 class="section-title"><?= $this->te('Progress per board') ?></h2>
            <?php if ($report->perBoard === []): ?><p class="muted small"><?= $this->te('No boards yet.') ?></p><?php endif; ?>
            <ul class="bars">
                <?php foreach ($report->perBoard as $row): ?>
                    <?php $percent = TeamReport::percent($row['done'], $row['open']); ?>
                    <li>
                        <span class="bar-label" dir="auto"><a href="<?= $this->url('/boards/' . $row['board']->publicId . '/dashboard') ?>"><?= $this->e($row['board']->name) ?></a></span>
                        <span class="bar-track" aria-hidden="true"><span class="bar bar-done" data-w="<?= $bar($percent, 100) ?>"></span></span>
                        <span class="bar-value"><?= $percent ?>%<?php if ($row['overdue'] > 0): ?> · <span class="danger"><?= $this->te(':count overdue', ['count' => (string) $row['overdue']]) ?></span><?php endif; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card stack">
            <h2 class="section-title"><?= $this->te('Completed per week') ?></h2>
            <div class="trend" role="img" aria-label="<?= $this->te(':count cards completed in the period', ['count' => (string) $t['completed']]) ?>">
                <?php foreach ($report->completedByWeek as $week => $count): ?>
                    <span class="trend-col" title="<?= $this->e($week) ?>: <?= $count ?>"><span class="trend-bar" data-h="<?= $bar($count, $weekMax) ?>"></span><small><?= $this->e(substr($week, 5)) ?></small></span>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <p class="muted small"><span class="legend legend-open"></span> <?= $this->te('Still open') ?> <span class="legend legend-done"></span> <?= $this->te('Done') ?></p>
</main>
