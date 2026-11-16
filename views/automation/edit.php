<?php
/**
 * Changing an automation. The form first shows what the changes would do,
 * next to what the automation does now; nothing is saved until "Save
 * changes" is pressed.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Access\Contract\BoardRole $role
 * @var \App\Modules\Automation\Contract\Automation $automation
 * @var array<string, mixed> $values
 * @var array{automation: \App\Modules\Automation\Contract\Automation, before: string, after: string}|null $preview
 * @var array{triggers: array<string, string>, conditions: array<string, string>, actions: array<string, string>, textActions: list<string>, lists: array<int, string>} $options
 */
$path = '/automations/' . $automation->publicId;
$back = '/boards/' . $board->publicId . '/automation';

/** Every submitted field as hidden inputs, nested arrays included. */
$hidden = function (array $fields, string $prefix = '') use (&$hidden): string {
    $html = '';
    foreach ($fields as $key => $value) {
        if ($prefix === '' && in_array($key, ['_token', 'confirm'], true)) {
            continue;
        }
        $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
        $html .= is_array($value)
            ? $hidden($value, $name)
            : '<input type="hidden" name="' . $this->e($name) . '" value="' . $this->e((string) $value) . '">';
    }

    return $html;
};
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('boards/view-bar', ['board' => $board, 'role' => $role, 'current' => '/automation', 'query' => []]) ?>

<main class="wrap view-page stack automation-page">
    <a class="back-link" href="<?= $this->url($back) ?>"><?= $this->icon('left') ?> <?= $this->te('Automation') ?></a>

    <?php if ($preview !== null): ?>
        <section class="card stack automation-preview" aria-labelledby="preview-title">
            <h2 id="preview-title" class="section-title"><?= $this->icon('eye') ?> <?= $this->te('Check the changes') ?></h2>
            <div class="compare">
                <div>
                    <h3 class="small muted"><?= $this->te('Now') ?></h3>
                    <p dir="auto"><?= $this->e($preview['before']) ?></p>
                </div>
                <div>
                    <h3 class="small muted"><?= $this->te('After saving') ?></h3>
                    <p dir="auto"><strong><?= $this->e($preview['after']) ?></strong></p>
                </div>
            </div>
            <?php if ($preview['before'] === $preview['after']): ?>
                <p class="muted small"><?= $this->te('It will do the same thing as now.') ?></p>
            <?php endif; ?>
            <form action="<?= $this->url($path) ?>" method="post" class="form-actions">
                <?= $this->csrfField() ?>
                <?= $hidden($values) ?>
                <input type="hidden" name="kind" value="<?= $this->e($automation->kind) ?>">
                <input type="hidden" name="confirm" value="1">
                <button class="btn btn-primary" type="submit"><?= $this->icon('check') ?> <?= $this->te('Save changes') ?></button>
                <a class="btn btn-ghost" href="<?= $this->url($back) ?>"><?= $this->te('Cancel') ?></a>
            </form>
        </section>
    <?php endif; ?>

    <section class="card stack">
        <h2 class="section-title"><?= $this->icon('edit') ?> <?= $this->te('Edit automation') ?></h2>
        <?php if (!$automation->enabled): ?>
            <p class="pill"><?= $this->icon('pause') ?><?= $this->te('Paused') ?></p>
        <?php endif; ?>
        <?= $this->partial('automation/form', [
            'kind' => $automation->kind, 'action' => $path, 'submit' => 'Preview changes',
            'values' => $values, 'options' => $options, 'cancel' => $back,
        ]) ?>
    </section>
</main>
