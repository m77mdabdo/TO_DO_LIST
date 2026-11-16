<?php
/**
 * Repeating due date, reminder, and scheduled copies of the card.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var list<\App\Modules\Board\Contract\ListSummary> $lists open lists of the board
 * @var array{frequency: string, weekdays: list<string>, month_day: int, hour: int, list_id: int, next_at: string, last_run_at: ?string, last_result: ?string}|null $repeat
 * @var bool $canEdit
 */
use App\Modules\Card\Contract\CardSummary;

$form = $this->form;
$zone = date_default_timezone_get();
$hours = range(0, 23);
$hourLabel = static fn (int $h): string => str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
?>
<section class="card stack" id="schedule" aria-labelledby="schedule-title">
    <h2 id="schedule-title" class="section-title"><?= $this->icon('calendar') ?> <?= $this->te('Repeat and reminders') ?></h2>

    <?php if (!$canEdit): ?>
        <p class="muted small">
            <?= $card->recurrence !== null ? $this->te(CardSummary::RECURRENCES[$card->recurrence] ?? '') : $this->te('The due date does not repeat.') ?>
            <?php if ($repeat !== null): ?><br><?= $this->te('A copy of this card is made on a schedule. Next copy: :date', ['date' => substr($repeat['next_at'], 0, 16)]) ?><?php endif; ?>
        </p>
    <?php else: ?>
        <form action="<?= $this->url('/cards/' . $card->publicId . '/schedule') ?>" method="post" class="stack-sm">
            <?= $this->csrfField() ?>
            <label><span><?= $this->te('Repeat the due date') ?></span>
                <select name="recurrence" aria-describedby="recurrence-help" <?= $card->dueAt === null && $card->recurrence === null ? 'disabled' : '' ?>>
                    <option value=""><?= $this->te('Does not repeat') ?></option>
                    <?php foreach (CardSummary::RECURRENCES as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $card->recurrence === $value ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <small class="muted" id="recurrence-help"><?= $card->dueAt === null
                ? $this->te('Give the card a due date first, then choose how it repeats.')
                : $this->te('When you mark the card done, its dates move to the next time and it stays open.') ?></small>
            <?= $this->partial('partials/field-error', ['field' => 'recurrence']) ?>

            <div class="field-row">
                <label><span><?= $this->te('Reminder') ?></span>
                    <select name="remind">
                        <?php foreach (CardSummary::REMINDERS as $value => $label): ?>
                            <option value="<?= $value ?>" <?= (string) $card->remind === (string) $value ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label><span><?= $this->te('At') ?></span>
                    <select name="remind_hour">
                        <?php foreach ($hours as $h): ?>
                            <option value="<?= $h ?>" <?= $card->remindHour === $h ? 'selected' : '' ?>><?= $hourLabel($h) ?></option>
                        <?php endforeach; ?>
                    </select></label>
            </div>
            <small class="muted"><?= $this->te('Reminders go to the people on the card and its watchers, at that hour in the :zone time zone. They stop when the card is done or archived.', ['zone' => $zone]) ?></small>
            <?= $this->partial('partials/field-error', ['field' => 'remind']) ?>
            <div><button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button></div>
        </form>

        <details class="repeat-copies" <?= $repeat !== null || $form->error('frequency') || $form->error('weekdays') || $form->error('list_id') ? 'open' : '' ?>>
            <summary class="btn btn-ghost"><?= $this->icon('copy') ?><?= $this->te('Make a new copy of this card on a schedule') ?></summary>
            <form action="<?= $this->url('/cards/' . $card->publicId . '/repeat') ?>" method="post" class="stack-sm">
                <?= $this->csrfField() ?>
                <label><span><?= $this->te('How often') ?></span>
                    <select name="frequency" class="js-repeat-frequency">
                        <option value=""><?= $this->te('Never') ?></option>
                        <?php foreach (CardSummary::REPEAT_FREQUENCIES as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($repeat['frequency'] ?? '') === $value ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <?= $this->partial('partials/field-error', ['field' => 'frequency']) ?>
                <fieldset class="weekday-choices js-repeat-weekly">
                    <legend><?= $this->te('Days of the week') ?></legend>
                    <?php foreach (CardSummary::WEEKDAYS as $value => $label): ?>
                        <label class="check"><input type="checkbox" name="weekdays[]" value="<?= $value ?>" <?= in_array((string) $value, $repeat['weekdays'] ?? [], true) ? 'checked' : '' ?>> <?= $this->te($label) ?></label>
                    <?php endforeach; ?>
                </fieldset>
                <?= $this->partial('partials/field-error', ['field' => 'weekdays']) ?>
                <div class="field-row">
                    <label class="js-repeat-monthly"><span><?= $this->te('Day of the month') ?></span>
                        <input type="number" name="month_day" min="1" max="31" value="<?= (int) ($repeat['month_day'] ?? 1) ?>" inputmode="numeric"></label>
                    <label><span><?= $this->te('At') ?></span>
                        <select name="hour">
                            <?php foreach ($hours as $h): ?>
                                <option value="<?= $h ?>" <?= (int) ($repeat['hour'] ?? 6) === $h ? 'selected' : '' ?>><?= $hourLabel($h) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    <label><span><?= $this->te('Into the list') ?></span>
                        <select name="list_id">
                            <?php foreach ($lists as $list): ?>
                                <option value="<?= $list->id ?>" <?= ($repeat['list_id'] ?? $card->listId) === $list->id ? 'selected' : '' ?>><?= $this->e($list->name) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                </div>
                <?= $this->partial('partials/field-error', ['field' => 'month_day']) ?>
                <?= $this->partial('partials/field-error', ['field' => 'list_id']) ?>
                <small class="muted"><?= $this->te('Each copy keeps the description, labels, checklists, people and custom fields. Times are in the :zone time zone. If the scheduler is off for a while, one copy is made for the time it missed, never several.', ['zone' => $zone]) ?></small>
                <?php if ($repeat !== null): ?>
                    <dl class="repeat-status small">
                        <div><dt><?= $this->te('Next copy') ?></dt><dd dir="ltr"><?= $this->e(substr($repeat['next_at'], 0, 16)) ?></dd></div>
                        <div><dt><?= $this->te('Last run') ?></dt><dd><?php if ($repeat['last_run_at'] === null): ?><?= $this->te('Not yet') ?><?php else: ?>
                            <span dir="ltr"><?= $this->e(substr($repeat['last_run_at'], 0, 16)) ?></span>:
                            <?= $repeat['last_result'] === 'ok' ? $this->te('A copy was made.') : $this->te('Failed') . ' (' . $this->te((string) $repeat['last_result']) . ')' ?><?php endif; ?></dd></div>
                    </dl>
                <?php endif; ?>
                <div><button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button></div>
            </form>
        </details>
    <?php endif; ?>
</section>
