<?php
/**
 * The personal week planner: when I plan to work on my cards. Planned time
 * is separate from the due date and never changes the card.
 *
 * @var \App\View\View $this
 * @var list<string> $days the seven days shown, Y-m-d
 * @var list<array{id: int, card: \App\Modules\Card\Contract\CardSummary, board: \App\Modules\Board\Contract\BoardSummary, day: string, hour: ?int, minutes: int}> $sessions
 * @var list<array{card: \App\Modules\Card\Contract\CardSummary, board: \App\Modules\Board\Contract\BoardSummary}> $unplanned
 * @var string $previous
 * @var string $next
 * @var array{created_at: string, last_used_at: ?string}|null $feed
 * @var ?string $feedUrl the new link, shown once right after it is made
 * @var \DateTimeImmutable $today
 */
$todayDate = $today->format('Y-m-d');
$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$durations = [30, 60, 90, 120, 180, 240];
$hourLabel = static fn (int $h): string => str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
$length = fn (int $minutes): string => $minutes < 60 ? $this->t(':count min', ['count' => (string) $minutes]) : $this->t(':count h', ['count' => rtrim(rtrim(number_format($minutes / 60, 1), '0'), '.')]);

// Rows: "any time", the usual working hours, and any other hour that has a session this week.
$hours = range(8, 19);
$byCell = [];
foreach ($sessions as $session) {
    $byCell[$session['day']][$session['hour'] ?? 'any'][] = $session;
    if ($session['hour'] !== null && !in_array($session['hour'], $hours, true)) {
        $hours[] = $session['hour'];
    }
}
sort($hours);
$rows = ['any', ...$hours];
$defaultDay = in_array($todayDate, $days, true) ? $todayDate : $days[0];
$form = $this->form;

$timeFields = function (string $day, ?int $hour, int $minutes, string $id) use ($hours, $hourLabel, $durations, $length): string {
    ob_start(); ?>
    <label><span><?= $this->te('Day') ?></span><input type="date" name="day" required value="<?= $this->e($day) ?>" id="<?= $id ?>-day"></label>
    <label><span><?= $this->te('Time') ?></span>
        <select name="hour">
            <option value=""><?= $this->te('Any time') ?></option>
            <?php foreach (range(0, 23) as $h): ?><option value="<?= $h ?>" <?= $hour === $h ? 'selected' : '' ?>><?= $hourLabel($h) ?></option><?php endforeach; ?>
        </select></label>
    <label><span><?= $this->te('For') ?></span>
        <select name="minutes">
            <?php foreach ($durations as $d): ?><option value="<?= $d ?>" <?= $minutes === $d ? 'selected' : '' ?>><?= $this->e($length($d)) ?></option><?php endforeach; ?>
        </select></label>
    <?php return (string) ob_get_clean();
};
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('workspaces/views-nav', ['current' => 'planner']) ?>

<main class="wrap view-page stack planner-page">
    <p class="muted small"><?= $this->te('Drag a task to a day and time to plan when you will work on it, or use the Plan button. Planned time is only for you and never changes the card’s due date.') ?></p>
    <?php foreach (['day', 'hour', 'minutes', 'card'] as $field): ?><?= $this->partial('partials/field-error', ['field' => $field]) ?><?php endforeach; ?>

    <div class="planner-layout">
        <section class="card planner-week" aria-labelledby="week-title">
            <header class="calendar-head">
                <a class="icon-btn" href="<?= $this->url('/planner?week=' . $previous) ?>" title="<?= $this->te('Previous week') ?>" aria-label="<?= $this->te('Previous week') ?>"><?= $this->icon($this->direction() === 'rtl' ? 'right' : 'left') ?></a>
                <h2 id="week-title"><bdi><?= $this->e($days[0]) ?></bdi> – <bdi><?= $this->e($days[6]) ?></bdi></h2>
                <a class="icon-btn" href="<?= $this->url('/planner?week=' . $next) ?>" title="<?= $this->te('Next week') ?>" aria-label="<?= $this->te('Next week') ?>"><?= $this->icon($this->direction() === 'rtl' ? 'left' : 'right') ?></a>
                <a class="btn btn-ghost" href="<?= $this->url('/planner') ?>"><?= $this->te('This week') ?></a>
            </header>
            <div class="table-wrap">
                <table class="planner-grid">
                    <caption class="visually-hidden"><?= $this->te('Your planned work time this week') ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><span class="visually-hidden"><?= $this->te('Time') ?></span></th>
                            <?php foreach ($days as $day): ?>
                                <th scope="col" class="<?= $day === $todayDate ? 'is-today' : '' ?>">
                                    <?= $this->te($dayNames[(int) (new DateTimeImmutable($day))->format('w')]) ?>
                                    <small><bdi><?= (int) substr($day, 8, 2) ?>/<?= (int) substr($day, 5, 2) ?></bdi></small>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr class="<?= $row === 'any' ? 'is-any-time' : '' ?>">
                                <th scope="row"><?= $row === 'any' ? $this->te('Any time') : '<bdi>' . $hourLabel($row) . '</bdi>' ?></th>
                                <?php foreach ($days as $day): ?>
                                    <td class="planner-cell js-plan-drop <?= $day === $todayDate ? 'is-today' : '' ?>" data-day="<?= $day ?>" data-hour="<?= $row === 'any' ? '' : $row ?>">
                                        <?php foreach ($byCell[$day][$row] ?? [] as $session): ?>
                                            <?php $card = $session['card']; $late = !$card->isComplete() && $card->dueDate() !== null && $card->dueDate() < $session['day']; ?>
                                            <div class="plan-chip js-plan-item <?= $card->isComplete() ? 'is-done' : '' ?>" draggable="true" data-session="<?= $session['id'] ?>" id="session-<?= $session['id'] ?>">
                                                <a href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto" class="plan-title"><?= $this->e($card->title) ?></a>
                                                <small class="muted" dir="auto"><?= $this->e($session['board']->name) ?> · <?= $this->e($length($session['minutes'])) ?></small>
                                                <?php if ($card->dueDate() !== null): ?>
                                                    <small class="plan-due <?= $late ? 'danger' : '' ?>"><?= $this->te($late ? 'Due before this day: :date' : 'Due :date', ['date' => $card->dueDate()]) ?></small>
                                                <?php endif; ?>
                                                <details class="menu plan-menu">
                                                    <summary class="icon-btn" aria-label="<?= $this->te('Change the plan for :card', ['card' => $card->title]) ?>"><?= $this->icon('more') ?></summary>
                                                    <div class="menu-panel stack-sm">
                                                        <form action="<?= $this->url('/planner/sessions/' . $session['id']) ?>" method="post" class="stack-sm">
                                                            <?= $this->csrfField() ?>
                                                            <?= $timeFields($session['day'], $session['hour'], $session['minutes'], 's' . $session['id']) ?>
                                                            <button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button>
                                                        </form>
                                                        <form action="<?= $this->url('/planner/sessions/' . $session['id'] . '/delete') ?>" method="post">
                                                            <?= $this->csrfField() ?>
                                                            <input type="hidden" name="week" value="<?= $this->e($days[0]) ?>">
                                                            <button class="btn btn-ghost" type="submit"><?= $this->icon('close') ?><?= $this->te('Take off the plan') ?></button>
                                                        </form>
                                                    </div>
                                                </details>
                                            </div>
                                        <?php endforeach; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="card stack-sm planner-tasks js-plan-unplan" aria-labelledby="unplanned-title" data-week="<?= $this->e($days[0]) ?>">
            <h2 class="section-title" id="unplanned-title"><?= $this->icon('checklist') ?> <?= $this->te('Not planned yet') ?> <span class="count"><?= count($unplanned) ?></span></h2>
            <p class="muted small"><?= $this->te('Open cards you are on, with no planned time from today on. Drop a planned task here to take it off the plan.') ?></p>
            <?php if ($unplanned === []): ?>
                <p class="muted small"><?= $this->te('Everything given to you has time planned.') ?></p>
            <?php endif; ?>
            <ul class="stack-sm">
                <?php foreach ($unplanned as ['card' => $card, 'board' => $board]): ?>
                    <li class="plan-chip js-plan-item" draggable="true" data-card="<?= $this->e($card->publicId) ?>">
                        <a href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto" class="plan-title"><?= $this->e($card->title) ?></a>
                        <small class="muted" dir="auto"><?= $this->e($board->name) ?></small>
                        <?php if ($card->dueDate() !== null): ?><small class="plan-due <?= $card->isOverdue($today) ? 'danger' : '' ?>"><?= $this->te('Due :date', ['date' => $card->dueDate()]) ?></small><?php endif; ?>
                        <details class="menu plan-menu">
                            <summary class="btn btn-ghost btn-sm"><?= $this->icon('clock') ?><?= $this->te('Plan') ?><span class="visually-hidden">: <?= $this->e($card->title) ?></span></summary>
                            <div class="menu-panel">
                                <form action="<?= $this->url('/planner/sessions') ?>" method="post" class="stack-sm">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="card" value="<?= $this->e($card->publicId) ?>">
                                    <?= $timeFields($defaultDay, null, 60, 'c' . $card->id) ?>
                                    <button class="btn btn-primary" type="submit"><?= $this->te('Plan') ?></button>
                                </form>
                            </div>
                        </details>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>
    </div>

    <form method="post" class="js-plan-form" hidden>
        <?= $this->csrfField() ?>
        <input type="hidden" name="card"><input type="hidden" name="day"><input type="hidden" name="hour"><input type="hidden" name="week" value="<?= $this->e($days[0]) ?>">
    </form>
    <div hidden class="js-plan-urls" data-create="<?= $this->url('/planner/sessions') ?>" data-session="<?= $this->url('/planner/sessions/') ?>"></div>

    <section class="card stack-sm" id="calendar-link" aria-labelledby="calendar-link-title">
        <h2 class="section-title" id="calendar-link-title"><?= $this->icon('calendar') ?> <?= $this->te('Your calendar link (iCal)') ?></h2>
        <p class="muted small"><?= $this->te('Add your due dates and planned time to Google Calendar, Outlook or Apple Calendar by subscribing to a private link. It shows only cards given to you on boards you can still open, from 30 days ago to 6 months ahead. Calendar apps usually refresh it every few hours.') ?></p>
        <?php if ($feedUrl !== null): ?>
            <div class="copy-row">
                <label class="visually-hidden" for="feed-url"><?= $this->te('Your calendar link') ?></label>
                <input id="feed-url" class="js-copy-source" readonly dir="ltr" value="<?= $this->e($feedUrl) ?>">
                <button class="btn btn-ghost js-copy" type="button" data-copied="<?= $this->te('Copied') ?>"><?= $this->icon('copy') ?><span><?= $this->te('Copy') ?></span></button>
            </div>
            <p class="small warning-text"><?= $this->te('Copy the link now: it is shown only once. Anyone with the link can read these dates, so keep it private.') ?></p>
        <?php endif; ?>
        <?php if ($feed === null): ?>
            <p class="small"><?= $this->te('No calendar link is on.') ?></p>
            <form action="<?= $this->url('/planner/calendar-link') ?>" method="post">
                <?= $this->csrfField() ?>
                <button class="btn btn-primary" type="submit"><?= $this->icon('link') ?><?= $this->te('Make a calendar link') ?></button>
            </form>
        <?php else: ?>
            <dl class="repeat-status small">
                <div><dt><?= $this->te('Made') ?></dt><dd dir="ltr"><?= $this->e(substr($feed['created_at'], 0, 16)) ?></dd></div>
                <div><dt><?= $this->te('Last read by a calendar app') ?></dt><dd><?= $feed['last_used_at'] !== null ? '<span dir="ltr">' . $this->e(substr($feed['last_used_at'], 0, 16)) . '</span>' : $this->te('Not yet') ?></dd></div>
            </dl>
            <div class="row-actions">
                <form action="<?= $this->url('/planner/calendar-link') ?>" method="post" data-confirm="<?= $this->te('Make a new link? The old link stops working and calendars using it stop updating.') ?>">
                    <?= $this->csrfField() ?>
                    <button class="btn btn-ghost" type="submit"><?= $this->icon('restore') ?><?= $this->te('Make a new link') ?></button>
                </form>
                <form action="<?= $this->url('/planner/calendar-link/delete') ?>" method="post" data-confirm="<?= $this->te('Turn off the calendar link? Calendars using it stop updating.') ?>">
                    <?= $this->csrfField() ?>
                    <button class="btn btn-ghost danger" type="submit"><?= $this->icon('close') ?><?= $this->te('Turn off') ?></button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</main>
