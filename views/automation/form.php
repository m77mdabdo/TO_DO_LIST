<?php
/**
 * The automation form, for a new automation and for changing one. Values
 * come from the kept input after an error, else from $values (the
 * automation being changed, or what was just previewed).
 *
 * @var \App\View\View $this
 * @var string $kind
 * @var string $action where the form posts
 * @var string $submit the button text
 * @var array<string, mixed> $values name, trigger, scope, every, weekday, monthday, hour, when, days, conditions (list), actions (list of {do, text})
 * @var array{triggers: array<string, string>, conditions: array<string, string>, actions: array<string, string>, textActions: list<string>, lists: array<int, string>} $options
 */
$form = $this->form;
$values ??= [];
$kept = $form->oldValue('kind') !== null;
$v = static function (string $field, string $default = '') use ($form, $kept, $values): string {
    if ($kept) {
        return $form->old($field, $default);
    }

    return is_scalar($values[$field] ?? null) ? (string) $values[$field] : $default;
};
$conditions = array_values(array_filter((array) ($kept ? $form->oldValue('conditions') : ($values['conditions'] ?? [])), 'is_string'));
$actionRows = array_values(array_filter((array) ($kept ? $form->oldValue('actions') : ($values['actions'] ?? [])), 'is_array'));
$weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$isButton = in_array($kind, ['card_button', 'board_button'], true);
?>
<form action="<?= $this->url($action) ?>" method="post" class="stack automation-form" novalidate>
    <?= $this->csrfField() ?>
    <input type="hidden" name="kind" value="<?= $this->e($kind) ?>">

    <label><span><?= $this->te($isButton ? 'Button name' : 'Name (optional)') ?></span>
        <input name="name" maxlength="120" dir="auto" value="<?= $this->e($v('name')) ?>" <?= $isButton ? 'required' : '' ?>
               placeholder="<?= $this->te($kind === 'card_button' ? 'e.g. Send for review' : (!$isButton ? 'Leave empty to describe it automatically' : 'e.g. Clean up Done')) ?>"
               aria-invalid="<?= $form->error('name') ? 'true' : 'false' ?>"></label>
    <?php if ($form->error('name')): ?><p class="field-error"><?= $this->te((string) $form->error('name')) ?></p><?php endif; ?>

    <?php if ($kind === 'rule'): ?>
        <label><span><?= $this->te('When') ?></span>
            <select name="trigger" required aria-invalid="<?= $form->error('trigger') ? 'true' : 'false' ?>">
                <?php foreach ($options['triggers'] as $value => $label): ?>
                    <option value="<?= $this->e($value) ?>" <?= $v('trigger') === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select></label>
        <?php if ($form->error('trigger')): ?><p class="field-error"><?= $this->te((string) $form->error('trigger')) ?></p><?php endif; ?>
    <?php endif; ?>

    <?php if ($kind === 'schedule'): ?>
        <div class="field-row">
            <label><span><?= $this->te('Every') ?></span>
                <select name="every">
                    <?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $v('every') === $value ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label><span><?= $this->te('On (weekly)') ?></span>
                <select name="weekday">
                    <?php foreach ($weekdays as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $v('weekday', '0') === (string) $value ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label><span><?= $this->te('On day (monthly)') ?></span>
                <select name="monthday">
                    <?php for ($d = 1; $d <= 28; $d++): ?>
                        <option value="<?= $d ?>" <?= $v('monthday', '1') === (string) $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endfor; ?>
                </select></label>
            <label><span><?= $this->te('At') ?></span>
                <select name="hour">
                    <?php for ($h = 0; $h <= 23; $h++): ?>
                        <option value="<?= $h ?>" <?= $v('hour', '8') === (string) $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                    <?php endfor; ?>
                </select></label>
        </div>
    <?php endif; ?>

    <?php if ($kind === 'due'): ?>
        <div class="field-row">
            <label><span><?= $this->te('When') ?></span>
                <select name="when">
                    <?php foreach (['before' => 'Before the due date', 'on' => 'On the due date', 'after' => 'After the due date (if not done)'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $v('when', 'before') === $value ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label><span><?= $this->te('Days') ?></span>
                <select name="days">
                    <?php foreach (\App\Modules\Automation\Contract\Automation::DUE_DAY_CHOICES as $days): ?>
                        <option value="<?= $days ?>" <?= $v('days', '1') === (string) $days ? 'selected' : '' ?>><?= $days ?></option>
                    <?php endforeach; ?>
                </select></label>
        </div>
    <?php endif; ?>

    <?php if (in_array($kind, ['board_button', 'schedule', 'due'], true)): ?>
        <label><span><?= $this->te('Which cards') ?></span>
            <select name="scope">
                <option value=""><?= $this->te('Every open card on the board') ?></option>
                <?php foreach ($options['lists'] as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $v('scope') === (string) $id ? 'selected' : '' ?>><?= $this->te('Cards in :list', ['list' => '“' . $name . '”']) ?></option>
                <?php endforeach; ?>
            </select></label>
        <?php if ($form->error('scope')): ?><p class="field-error"><?= $this->te((string) $form->error('scope')) ?></p><?php endif; ?>
    <?php endif; ?>

    <?php if ($kind !== 'card_button'): ?>
        <fieldset class="stack-sm">
            <legend><?= $this->te('Only if (optional)') ?></legend>
            <?php for ($i = 0; $i < max(3, count($conditions)); $i++): ?>
                <select name="conditions[]" aria-label="<?= $this->te('Condition :n', ['n' => (string) ($i + 1)]) ?>">
                    <option value=""><?= $this->te('No condition') ?></option>
                    <?php foreach ($options['conditions'] as $value => $label): ?>
                        <option value="<?= $this->e($value) ?>" <?= ($conditions[$i] ?? null) === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endfor; ?>
            <?php if ($form->error('conditions')): ?><p class="field-error"><?= $this->te((string) $form->error('conditions')) ?></p><?php endif; ?>
        </fieldset>
    <?php endif; ?>

    <fieldset class="stack-sm">
        <legend><?= $this->te('Then') ?></legend>
        <?php for ($i = 0; $i < max(4, count($actionRows)); $i++): $row = $actionRows[$i] ?? []; ?>
            <div class="action-row">
                <select name="actions[<?= $i ?>][do]" aria-label="<?= $this->te('Action :n', ['n' => (string) ($i + 1)]) ?>">
                    <option value=""><?= $this->te($i === 0 ? 'Choose an action' : 'Nothing more') ?></option>
                    <?php foreach ($options['actions'] as $value => $label): ?>
                        <?php if ($kind === 'card_button' && str_starts_with($value, 'list.')) {
                            continue;
                        } ?>
                        <option value="<?= $this->e($value) ?>" <?= (string) ($row['do'] ?? '') === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <input name="actions[<?= $i ?>][text]" maxlength="2000" dir="auto" value="<?= $this->e((string) ($row['text'] ?? '')) ?>"
                       aria-label="<?= $this->te('Text for action :n', ['n' => (string) ($i + 1)]) ?>"
                       placeholder="<?= $this->te('Text, for a comment, a notice or a checklist') ?>">
            </div>
        <?php endfor; ?>
        <p class="muted small"><?= $this->te('For a checklist, write its name, then the items separated by new lines or by " | ".') ?></p>
        <?php if ($form->error('actions')): ?><p class="field-error"><?= $this->te((string) $form->error('actions')) ?></p><?php endif; ?>
    </fieldset>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $this->icon('check') ?> <?= $this->te($submit) ?></button>
        <?php if (!empty($cancel)): ?>
            <a class="btn btn-ghost" href="<?= $this->url($cancel) ?>"><?= $this->te('Cancel') ?></a>
        <?php endif; ?>
    </div>
</form>
