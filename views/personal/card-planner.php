<?php
/**
 * "Plan work time" on the card page (slot "card.side", Personal module).
 * Planned time belongs to the viewer only and never changes the due date.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var list<array{id: int, day: string, hour: ?int, minutes: int}> $planned the viewer's sessions on this card from today on
 * @var list<int> $durations
 * @var \DateTimeImmutable $today
 */
if ($card->archived || $card->kind !== 'card') {
    return;
}
$hourLabel = static fn (int $h): string => str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
$length = fn (int $minutes): string => $minutes < 60 ? $this->t(':count min', ['count' => (string) $minutes]) : $this->t(':count h', ['count' => rtrim(rtrim(number_format($minutes / 60, 1), '0'), '.')]);
?>
<section class="card stack-sm" id="planner" aria-labelledby="planner-title">
    <h2 id="planner-title" class="section-title"><?= $this->icon('clock') ?> <?= $this->te('Plan work time') ?></h2>
    <?php if ($planned !== []): ?>
        <ul class="planned-list">
            <?php foreach ($planned as $session): ?>
                <li>
                    <a href="<?= $this->url('/planner?week=' . $session['day'] . '#session-' . $session['id']) ?>"><bdi><?= $this->e($session['day']) ?></bdi>
                        <?= $session['hour'] === null ? $this->te('Any time') : '<bdi>' . $hourLabel($session['hour']) . '</bdi>' ?> · <?= $this->e($length($session['minutes'])) ?></a>
                    <form action="<?= $this->url('/planner/sessions/' . $session['id'] . '/delete') ?>" method="post">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="back" value="card"><input type="hidden" name="card" value="<?= $this->e($card->publicId) ?>">
                        <button class="icon-btn" type="submit" title="<?= $this->te('Take off the plan') ?>" aria-label="<?= $this->te('Take off the plan') ?>: <?= $this->e($session['day']) ?>"><?= $this->icon('close') ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <details class="plan-card" <?= $this->form->error('day') || $this->form->error('minutes') ? 'open' : '' ?>>
        <summary class="btn btn-ghost"><?= $this->icon('plus') ?><?= $this->te('Plan time to work on this') ?></summary>
        <form action="<?= $this->url('/planner/sessions') ?>" method="post" class="stack-sm">
            <?= $this->csrfField() ?>
            <input type="hidden" name="card" value="<?= $this->e($card->publicId) ?>"><input type="hidden" name="back" value="card">
            <div class="field-row">
                <label><span><?= $this->te('Day') ?></span><input type="date" name="day" required value="<?= $this->e($this->form->old('day', $today->format('Y-m-d'))) ?>"></label>
                <label><span><?= $this->te('Time') ?></span>
                    <select name="hour">
                        <option value=""><?= $this->te('Any time') ?></option>
                        <?php foreach (range(0, 23) as $h): ?><option value="<?= $h ?>"><?= $hourLabel($h) ?></option><?php endforeach; ?>
                    </select></label>
                <label><span><?= $this->te('For') ?></span>
                    <select name="minutes">
                        <?php foreach ($durations as $d): ?><option value="<?= $d ?>" <?= $d === 60 ? 'selected' : '' ?>><?= $this->e($length($d)) ?></option><?php endforeach; ?>
                    </select></label>
            </div>
            <?= $this->partial('partials/field-error', ['field' => 'day']) ?>
            <?= $this->partial('partials/field-error', ['field' => 'minutes']) ?>
            <small class="muted"><?= $this->te('Only you see this. It shows in your planner and calendar link, and the due date stays as it is.') ?></small>
            <div><button class="btn btn-primary" type="submit"><?= $this->te('Plan') ?></button></div>
        </form>
    </details>
</section>
