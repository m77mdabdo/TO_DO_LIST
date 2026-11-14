<?php
/**
 * A board's automations: what is set up, a form to add one, and the run log.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Access\Contract\BoardRole $role
 * @var list<\App\Modules\Automation\Contract\Automation> $automations
 * @var array<int, string> $described automation id => sentence
 * @var list<\App\Modules\Automation\Contract\AutomationRun> $runs
 * @var array<int, string> $cardTitles
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var array{triggers: array<string, string>, conditions: array<string, string>, actions: array<string, string>, textActions: list<string>, lists: array<int, string>} $options
 * @var string $kind the kind the form is for
 * @var \App\Support\TimeAgo $timeAgo
 */
$form = $this->form;
$kinds = [
    'rule'         => ['bolt', 'Rules', 'Run by themselves when something happens on a card.'],
    'card_button'  => ['play', 'Card buttons', 'A button on every card that does several things at once.'],
    'board_button' => ['board', 'Board buttons', 'A button in the board bar that works on many cards.'],
    'schedule'     => ['clock', 'Schedules', 'Run every day, week or month.'],
    'due'          => ['calendar', 'Due dates', 'Run before, on or after the day a card is due.'],
];
$byKind = [];
foreach ($automations as $automation) {
    $byKind[$automation->kind][] = $automation;
}
$weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$detail = function (string $json): string {
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['text'])) {
        return $json;
    }
    $values = array_map(fn ($v) => is_string($v) ? $this->t($v) : (string) $v, (array) ($data['values'] ?? []));

    return $this->t((string) $data['text'], $values);
};
$base = '/boards/' . $board->publicId . '/automation';
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('boards/view-bar', ['board' => $board, 'role' => $role, 'current' => '/automation', 'query' => []]) ?>

<main class="wrap view-page stack automation-page">
    <section class="card stack">
        <h2 class="section-title"><?= $this->icon('bolt') ?> <?= $this->te('Automation') ?></h2>
        <p class="muted"><?= $this->te('Automations do routine work for you. They act with the permissions of the person who set them up (or who pressed the button), and everything they do is written in the run log below.') ?></p>

        <?php foreach ($kinds as $key => [$icon, $label, $help]): ?>
            <?php if (!isset($byKind[$key])) {
                continue;
            } ?>
            <h3 class="automation-kind"><?= $this->icon($icon) ?> <?= $this->te($label) ?></h3>
            <ul class="automation-list">
                <?php foreach ($byKind[$key] as $automation): ?>
                    <li class="automation-item <?= $automation->enabled ? '' : 'is-paused' ?>">
                        <div class="automation-text">
                            <strong dir="auto"><?= $this->e($automation->name) ?></strong>
                            <?php if (!$automation->enabled): ?><span class="pill"><?= $this->icon('pause') ?><?= $this->te('Paused') ?></span><?php endif; ?>
                            <p dir="auto"><?= $this->e($described[$automation->id] ?? '') ?></p>
                            <p class="muted small">
                                <?= $this->te('Set up by :name', ['name' => $people[$automation->createdBy]->name ?? $this->t('Someone')]) ?>
                                <?php if ($automation->lastRunAt !== null): ?> · <?= $this->te('last ran :when', ['when' => $timeAgo->format(new DateTimeImmutable($automation->lastRunAt))]) ?><?php endif; ?>
                            </p>
                        </div>
                        <div class="automation-tools">
                            <form action="<?= $this->url('/automations/' . $automation->publicId . '/toggle') ?>" method="post">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="on" value="<?= $automation->enabled ? '0' : '1' ?>">
                                <button class="btn btn-ghost" type="submit"><?= $this->icon($automation->enabled ? 'pause' : 'play') ?><span><?= $this->te($automation->enabled ? 'Pause' : 'Turn on') ?></span></button>
                            </form>
                            <form action="<?= $this->url('/automations/' . $automation->publicId . '/delete') ?>" method="post">
                                <?= $this->csrfField() ?>
                                <button class="btn btn-ghost btn-danger" type="submit"><?= $this->icon('trash') ?><span class="visually-hidden"><?= $this->te('Delete') ?></span></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
        <?php if ($automations === []): ?>
            <p class="empty-note"><?= $this->te('Nothing is automated on this board yet. Add your first automation below.') ?></p>
        <?php endif; ?>
    </section>

    <section class="card stack" id="new">
        <h2 class="section-title"><?= $this->icon('plus') ?> <?= $this->te('New automation') ?></h2>
        <nav class="view-tabs kind-tabs" aria-label="<?= $this->te('Kind of automation') ?>">
            <?php foreach ($kinds as $key => [$icon, $label]): ?>
                <a href="<?= $this->url($base . '?kind=' . $key . '#new') ?>" class="<?= $key === $kind ? 'is-active' : '' ?>" <?= $key === $kind ? 'aria-current="page"' : '' ?>><?= $this->icon($icon) ?><span><?= $this->te($label) ?></span></a>
            <?php endforeach; ?>
        </nav>
        <p class="muted"><?= $this->te($kinds[$kind][2]) ?></p>

        <form action="<?= $this->url($base) ?>" method="post" class="stack automation-form" novalidate>
            <?= $this->csrfField() ?>
            <input type="hidden" name="kind" value="<?= $this->e($kind) ?>">

            <label><span><?= $this->te(in_array($kind, ['card_button', 'board_button'], true) ? 'Button name' : 'Name (optional)') ?></span>
                <input name="name" maxlength="120" dir="auto" value="<?= $this->e($form->old('name')) ?>" <?= in_array($kind, ['card_button', 'board_button'], true) ? 'required' : '' ?>
                       placeholder="<?= $this->te($kind === 'card_button' ? 'e.g. Send for review' : (in_array($kind, ['rule', 'schedule', 'due'], true) ? 'Leave empty to describe it automatically' : 'e.g. Clean up Done')) ?>"
                       aria-invalid="<?= $form->error('name') ? 'true' : 'false' ?>"></label>
            <?php if ($form->error('name')): ?><p class="field-error"><?= $this->te((string) $form->error('name')) ?></p><?php endif; ?>

            <?php if ($kind === 'rule'): ?>
                <label><span><?= $this->te('When') ?></span>
                    <select name="trigger" required aria-invalid="<?= $form->error('trigger') ? 'true' : 'false' ?>">
                        <?php foreach ($options['triggers'] as $value => $label): ?>
                            <option value="<?= $this->e($value) ?>" <?= $form->old('trigger') === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <?php if ($form->error('trigger')): ?><p class="field-error"><?= $this->te((string) $form->error('trigger')) ?></p><?php endif; ?>
            <?php endif; ?>

            <?php if ($kind === 'schedule'): ?>
                <div class="field-row">
                    <label><span><?= $this->te('Every') ?></span>
                        <select name="every">
                            <?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $form->old('every') === $value ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    <label><span><?= $this->te('On (weekly)') ?></span>
                        <select name="weekday">
                            <?php foreach ($weekdays as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $form->old('weekday', '0') === (string) $value ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    <label><span><?= $this->te('On day (monthly)') ?></span>
                        <select name="monthday">
                            <?php for ($d = 1; $d <= 28; $d++): ?>
                                <option value="<?= $d ?>" <?= $form->old('monthday', '1') === (string) $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endfor; ?>
                        </select></label>
                    <label><span><?= $this->te('At') ?></span>
                        <select name="hour">
                            <?php for ($h = 0; $h <= 23; $h++): ?>
                                <option value="<?= $h ?>" <?= $form->old('hour', '8') === (string) $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                            <?php endfor; ?>
                        </select></label>
                </div>
            <?php endif; ?>

            <?php if ($kind === 'due'): ?>
                <div class="field-row">
                    <label><span><?= $this->te('When') ?></span>
                        <select name="when">
                            <?php foreach (['before' => 'Before the due date', 'on' => 'On the due date', 'after' => 'After the due date (if not done)'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $form->old('when', 'before') === $value ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    <label><span><?= $this->te('Days') ?></span>
                        <select name="days">
                            <?php foreach (\App\Modules\Automation\Contract\Automation::DUE_DAY_CHOICES as $days): ?>
                                <option value="<?= $days ?>" <?= $form->old('days', '1') === (string) $days ? 'selected' : '' ?>><?= $days ?></option>
                            <?php endforeach; ?>
                        </select></label>
                </div>
            <?php endif; ?>

            <?php if (in_array($kind, ['board_button', 'schedule', 'due'], true)): ?>
                <label><span><?= $this->te('Which cards') ?></span>
                    <select name="scope">
                        <option value=""><?= $this->te('Every open card on the board') ?></option>
                        <?php foreach ($options['lists'] as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $form->old('scope') === (string) $id ? 'selected' : '' ?>><?= $this->te('Cards in :list', ['list' => '“' . $name . '”']) ?></option>
                        <?php endforeach; ?>
                    </select></label>
            <?php endif; ?>

            <?php if ($kind !== 'card_button'): ?>
                <fieldset class="stack-sm">
                    <legend><?= $this->te('Only if (optional)') ?></legend>
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <select name="conditions[]" aria-label="<?= $this->te('Condition :n', ['n' => (string) ($i + 1)]) ?>">
                            <option value=""><?= $this->te('No condition') ?></option>
                            <?php foreach ($options['conditions'] as $value => $label): ?>
                                <option value="<?= $this->e($value) ?>"><?= $this->e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endfor; ?>
                    <?php if ($form->error('conditions')): ?><p class="field-error"><?= $this->te((string) $form->error('conditions')) ?></p><?php endif; ?>
                </fieldset>
            <?php endif; ?>

            <fieldset class="stack-sm">
                <legend><?= $this->te('Then') ?></legend>
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="action-row">
                        <select name="actions[<?= $i ?>][do]" aria-label="<?= $this->te('Action :n', ['n' => (string) ($i + 1)]) ?>">
                            <option value=""><?= $this->te($i === 0 ? 'Choose an action' : 'Nothing more') ?></option>
                            <?php foreach ($options['actions'] as $value => $label): ?>
                                <?php if ($kind === 'card_button' && str_starts_with($value, 'list.')) {
                                    continue;
                                } ?>
                                <option value="<?= $this->e($value) ?>"><?= $this->e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input name="actions[<?= $i ?>][text]" maxlength="2000" dir="auto" aria-label="<?= $this->te('Text for action :n', ['n' => (string) ($i + 1)]) ?>"
                               placeholder="<?= $this->te('Text, for a comment, a notice or a checklist') ?>">
                    </div>
                <?php endfor; ?>
                <p class="muted small"><?= $this->te('For a checklist, write its name, then the items separated by new lines or by " | ".') ?></p>
                <?php if ($form->error('actions')): ?><p class="field-error"><?= $this->te((string) $form->error('actions')) ?></p><?php endif; ?>
            </fieldset>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $this->icon('check') ?> <?= $this->te('Save automation') ?></button>
            </div>
        </form>
    </section>

    <section class="card stack" id="log">
        <h2 class="section-title"><?= $this->icon('activity') ?> <?= $this->te('Run log') ?></h2>
        <?php if ($runs === []): ?>
            <p class="empty-note"><?= $this->te('Nothing has run yet.') ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table run-log">
                    <thead><tr>
                        <th scope="col"><?= $this->te('When') ?></th>
                        <th scope="col"><?= $this->te('Automation') ?></th>
                        <th scope="col"><?= $this->te('Card') ?></th>
                        <th scope="col"><?= $this->te('Result') ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($runs as $run): ?>
                            <tr class="run-<?= $this->e($run->status) ?>">
                                <td><?= $this->e($timeAgo->format(new DateTimeImmutable($run->createdAt))) ?></td>
                                <td dir="auto"><?= $this->e($run->automationName) ?></td>
                                <td dir="auto"><?= $run->cardId !== null ? $this->e($cardTitles[$run->cardId] ?? '') : '–' ?></td>
                                <td><span class="pill run-status"><?= $this->te(['ok' => 'Done', 'failed' => 'Failed', 'skipped' => 'Skipped'][$run->status] ?? $run->status) ?></span>
                                    <span dir="auto"><?= $this->e($detail($run->detail)) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
