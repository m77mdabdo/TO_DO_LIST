<?php
/**
 * The board over five weeks: each dated card is a bar from its start to its
 * due date (a card with only one date is a one-day bar).
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Access\Contract\BoardRole $role
 * @var list<\App\Modules\Board\Contract\ListSummary> $lists
 * @var list<\DateTimeImmutable> $days
 * @var array<int, list<array{card: \App\Modules\Card\Contract\CardSummary, column: int, span: int, before: bool, after: bool}>> $rows list id => bars
 * @var int $undated
 * @var string $previous
 * @var string $next
 * @var ?int $todayCol
 * @var array<string, string> $query
 * @var \DateTimeImmutable $today
 */
$base = '/boards/' . $board->publicId . '/timeline';
$weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$link = fn (string $from): string => $this->url($base . '?' . http_build_query($query + ['from' => $from]));
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('boards/view-bar', ['board' => $board, 'role' => $role, 'current' => '/timeline', 'query' => $query]) ?>

<div class="board-tools">
    <?= $this->partial('boards/filters', compact('board', 'filter', 'boardLabels', 'audience', 'people', 'totalCards', 'shownCards', 'viewPath')) ?>
</div>

<main class="wrap view-page stack">
    <header class="calendar-head card">
        <a class="icon-btn" href="<?= $this->e($link($previous)) ?>" title="<?= $this->te('Earlier') ?>" aria-label="<?= $this->te('Earlier') ?>"><?= $this->icon($this->direction() === 'rtl' ? 'right' : 'left') ?></a>
        <h2><bdi><?= $this->e($days[0]->format('Y-m-d')) ?></bdi> – <bdi><?= $this->e(end($days)->format('Y-m-d')) ?></bdi></h2>
        <a class="icon-btn" href="<?= $this->e($link($next)) ?>" title="<?= $this->te('Later') ?>" aria-label="<?= $this->te('Later') ?>"><?= $this->icon($this->direction() === 'rtl' ? 'left' : 'right') ?></a>
        <a class="btn btn-ghost" href="<?= $this->e($this->url($base . ($query ? '?' . http_build_query($query) : ''))) ?>"><?= $this->te('This week') ?></a>
    </header>

    <div class="timeline-wrap card">
        <div class="timeline" role="table" aria-label="<?= $this->te('Timeline') ?>">
            <div class="timeline-row timeline-days" role="row">
                <span class="timeline-name" role="columnheader"></span>
                <div class="timeline-grid">
                    <?php foreach ($days as $i => $day): ?>
                        <span class="tl-day tl-c<?= $i + 1 ?> <?= $todayCol === $i + 1 ? 'is-today' : '' ?> <?= in_array((int) $day->format('w'), $this->locale() === 'ar' ? [5, 6] : [0, 6], true) ? 'is-weekend' : '' ?>" role="columnheader">
                            <?php if ($i % 7 === 0 || $todayCol === $i + 1): ?><small><?= $this->te($weekdays[(int) $day->format('w')]) ?></small><?php else: ?><small aria-hidden="true">&nbsp;</small><?php endif; ?><?= (int) $day->format('j') ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php $shown = 0; ?>
            <?php foreach ($lists as $list): ?>
                <?php if (empty($rows[$list->id])) {
                    continue;
                } ?>
                <div class="timeline-list" role="row"><span role="cell" dir="auto"><?= $this->e($list->name) ?></span></div>
                <?php foreach ($rows[$list->id] as $row): ?>
                    <?php $shown++; $card = $row['card']; $late = $card->isOverdue($today); ?>
                    <div class="timeline-row" role="row">
                        <a class="timeline-name" role="rowheader" href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"><?= $this->e($card->title) ?></a>
                        <div class="timeline-grid" role="cell">
                            <?php if ($todayCol !== null): ?><span class="tl-today tl-c<?= $todayCol ?>" aria-hidden="true"></span><?php endif; ?>
                            <a class="tl-bar tl-c<?= $row['column'] ?> tl-s<?= $row['span'] ?> <?= $card->isComplete() ? 'is-done' : ($late ? 'is-late' : '') ?> <?= $row['before'] ? 'cut-start' : '' ?> <?= $row['after'] ? 'cut-end' : '' ?>"
                               href="<?= $this->url('/cards/' . $card->publicId) ?>" title="<?= $this->e(trim(($card->startDate() ?? '') . ' → ' . ($card->dueDate() ?? ''), ' →')) ?>">
                                <span dir="auto"><?= $this->e($card->title) ?></span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?php if ($shown === 0): ?>
            <p class="muted center"><?= $this->te('No card has dates in these five weeks.') ?></p>
        <?php endif; ?>
    </div>
    <?php if ($undated > 0): ?>
        <p class="muted small"><?= $this->te(':count cards have no start or due date, so they are not on the timeline.', ['count' => (string) $undated]) ?></p>
    <?php endif; ?>
</main>
