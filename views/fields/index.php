<?php
/**
 * A board's custom fields: what they are, and forms to change them.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Access\Contract\BoardRole $role
 * @var list<\App\Modules\Field\Contract\CustomField> $fields
 * @var array<int, int> $usage field id => cards with a value
 * @var bool $canManage
 * @var int|null $editing the field whose form had an error
 */
use App\Modules\Field\Contract\CustomField;

$base = '/boards/' . $board->publicId . '/fields';
$form = $this->form;
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow stack fields-page">
    <a class="back-link" href="<?= $this->url('/boards/' . $board->publicId) ?>"><?= $this->icon('left') ?> <span dir="auto"><?= $this->e($board->name) ?></span></a>

    <section class="card stack">
        <h1 class="section-title"><?= $this->icon('table') ?> <?= $this->te('Custom fields') ?></h1>
        <p class="muted small"><?= $this->te('Fields every card on this board can fill in: text, a number, a date, a choice from a list, or a checkbox. Up to 20 fields, and 30 choices per field.') ?></p>

        <?php if (!$fields): ?>
            <p class="muted"><?= $this->te('This board has no custom fields yet.') ?></p>
        <?php endif; ?>

        <ol class="field-defs">
            <?php foreach ($fields as $i => $field): ?>
                <li class="field-def" id="field-<?= $field->id ?>">
                    <div class="field-def-head">
                        <strong dir="auto"><?= $this->e($field->name) ?></strong>
                        <span class="pill"><?= $this->te(CustomField::TYPES[$field->type] ?? $field->type) ?></span>
                        <?php if ($field->onCard): ?><span class="pill"><?= $this->icon('eye') ?><?= $this->te('On the cards') ?></span><?php endif; ?>
                        <small class="muted"><?= $this->te(':count cards have a value', ['count' => (string) ($usage[$field->id] ?? 0)]) ?></small>
                    </div>
                    <?php if ($canManage): ?>
                        <div class="field-def-actions">
                            <?php foreach ([['up', '1', 'Move up', $i > 0], ['down', '0', 'Move down', $i < count($fields) - 1]] as [$icon, $up, $label, $possible]): ?>
                                <?php if ($possible): ?>
                                    <form action="<?= $this->url($base . '/' . $field->id . '/move') ?>" method="post">
                                        <?= $this->csrfField() ?>
                                        <input type="hidden" name="up" value="<?= $up ?>">
                                        <button class="icon-btn" type="submit" title="<?= $this->te($label) ?>" aria-label="<?= $this->te($label) ?>: <?= $this->e($field->name) ?>"><?= $this->icon($icon) ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <details class="field-edit" <?= ($editing ?? null) === $field->id ? 'open' : '' ?>>
                                <summary class="link-btn"><?= $this->te('Edit') ?></summary>
                                <form action="<?= $this->url($base . '/' . $field->id) ?>" method="post" class="stack-sm">
                                    <?= $this->csrfField() ?>
                                    <label><span><?= $this->te('Name') ?></span>
                                        <input name="name" required maxlength="60" dir="auto" value="<?= $this->e($field->name) ?>"></label>
                                    <?php if ($field->type === 'select'): ?>
                                        <label><span><?= $this->te('Choices, one per line') ?></span>
                                            <textarea name="options" rows="5" dir="auto"><?= $this->e(implode("\n", array_column($field->options, 'name'))) ?></textarea></label>
                                        <label class="check"><input type="checkbox" name="confirm" value="1"> <?= $this->te('Remove it from those cards') ?></label>
                                        <p class="muted small"><?= $this->te('Removing a choice clears it from the cards that use it. Changing a choice’s text counts as removing it and adding a new one.') ?></p>
                                    <?php endif; ?>
                                    <label class="check"><input type="checkbox" name="on_card" value="1" <?= $field->onCard ? 'checked' : '' ?>> <?= $this->te('Show on the cards of the board') ?></label>
                                    <div><button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button></div>
                                </form>
                                <form action="<?= $this->url($base . '/' . $field->id . '/delete') ?>" method="post" class="field-delete"
                                      data-confirm="<?= $this->te('Delete the field :name? :count cards lose their value. This cannot be undone.', ['name' => $field->name, 'count' => (string) ($usage[$field->id] ?? 0)]) ?>">
                                    <?= $this->csrfField() ?>
                                    <p class="muted small"><?= $this->te('Deleting the field removes it and its value from every card (:count cards).', ['count' => (string) ($usage[$field->id] ?? 0)]) ?></p>
                                    <button class="btn btn-ghost danger" type="submit"><?= $this->icon('trash') ?><?= $this->te('Delete field') ?></button>
                                </form>
                            </details>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if ($form->error('options') || $form->error('name')): ?>
            <p class="field-error" role="alert"><?= $this->te((string) ($form->error('options') ?? $form->error('name'))) ?></p>
        <?php endif; ?>
    </section>

    <?php if ($canManage && count($fields) < CustomField::MAX_PER_BOARD): ?>
        <section class="card stack" id="new-field" aria-labelledby="new-field-title">
            <h2 id="new-field-title" class="section-title"><?= $this->icon('plus') ?> <?= $this->te('New field') ?></h2>
            <form action="<?= $this->url($base) ?>" method="post" class="stack-sm" novalidate>
                <?= $this->csrfField() ?>
                <div class="field-row">
                    <label><span><?= $this->te('Name') ?></span>
                        <input name="name" required maxlength="60" dir="auto" value="<?= $this->e($form->old('name')) ?>" placeholder="<?= $this->te('e.g. Priority') ?>"></label>
                    <label><span><?= $this->te('Type') ?></span>
                        <select name="type" class="js-field-type">
                            <?php foreach (CustomField::TYPES as $type => $label): ?>
                                <option value="<?= $type ?>" <?= $form->old('type', 'text') === $type ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                </div>
                <label class="js-field-options"><span><?= $this->te('Choices, one per line (for a choice from a list)') ?></span>
                    <textarea name="options" rows="4" dir="auto"><?= $this->e($form->old('options')) ?></textarea></label>
                <label class="check"><input type="checkbox" name="on_card" value="1" checked> <?= $this->te('Show on the cards of the board') ?></label>
                <div><button class="btn btn-primary" type="submit"><?= $this->icon('plus') ?><?= $this->te('Add field') ?></button></div>
            </form>
        </section>
    <?php endif; ?>
</main>
