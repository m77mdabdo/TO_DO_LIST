<?php
/**
 * The card's custom fields (slot "card.side", filled by the Field module).
 *
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var list<\App\Modules\Field\Contract\CustomField> $fields
 * @var array<int, string> $values field id => stored value
 * @var bool $canFill
 * @var bool $canManage
 */
if (!$fields && !$canManage) {
    return;
}
$form = $this->form;
$kept = $form->oldValue('field');
$value = static function (int $fieldId) use ($kept, $values): string {
    if (is_array($kept)) {
        return is_scalar($kept[$fieldId] ?? null) ? (string) $kept[$fieldId] : '';
    }

    return $values[$fieldId] ?? '';
};
?>
<section class="card stack" id="fields" aria-labelledby="fields-title">
    <h2 id="fields-title" class="section-title"><?= $this->icon('table') ?> <?= $this->te('Custom fields') ?></h2>

    <?php if (!$fields): ?>
        <p class="muted small"><?= $this->te('This board has no custom fields yet.') ?></p>
    <?php elseif ($canFill): ?>
        <form action="<?= $this->url('/cards/' . $card->publicId . '/fields') ?>" method="post" class="stack-sm field-values" novalidate>
            <?= $this->csrfField() ?>
            <?php foreach ($fields as $field): ?>
                <?php $id = 'field-' . $field->id; $error = $form->error('field_' . $field->id); ?>
                <?php if ($field->type === 'checkbox'): ?>
                    <input type="hidden" name="field[<?= $field->id ?>]" value="">
                    <label class="check"><input type="checkbox" name="field[<?= $field->id ?>]" value="1" <?= $value($field->id) === '1' ? 'checked' : '' ?>>
                        <bdi><?= $this->e($field->name) ?></bdi></label>
                <?php else: ?>
                    <label for="<?= $id ?>"><span dir="auto"><?= $this->e($field->name) ?></span>
                        <?php if ($field->type === 'select'): ?>
                            <select id="<?= $id ?>" name="field[<?= $field->id ?>]" aria-invalid="<?= $error ? 'true' : 'false' ?>">
                                <option value=""><?= $this->te('Not set') ?></option>
                                <?php foreach ($field->options as $option): ?>
                                    <option value="<?= $this->e($option['id']) ?>" <?= $value($field->id) === $option['id'] ? 'selected' : '' ?>><?= $this->e($option['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($field->type === 'date'): ?>
                            <input id="<?= $id ?>" type="date" name="field[<?= $field->id ?>]" value="<?= $this->e($value($field->id)) ?>" aria-invalid="<?= $error ? 'true' : 'false' ?>">
                        <?php elseif ($field->type === 'number'): ?>
                            <input id="<?= $id ?>" type="text" inputmode="decimal" name="field[<?= $field->id ?>]" value="<?= $this->e($value($field->id)) ?>" dir="ltr" aria-invalid="<?= $error ? 'true' : 'false' ?>">
                        <?php else: ?>
                            <input id="<?= $id ?>" name="field[<?= $field->id ?>]" maxlength="500" dir="auto" value="<?= $this->e($value($field->id)) ?>" aria-invalid="<?= $error ? 'true' : 'false' ?>">
                        <?php endif; ?>
                    </label>
                <?php endif; ?>
                <?php if ($error): ?><p class="field-error" role="alert"><?= $this->te((string) $error) ?></p><?php endif; ?>
            <?php endforeach; ?>
            <div><button class="btn btn-primary" type="submit"><?= $this->te('Save fields') ?></button></div>
        </form>
    <?php else: ?>
        <dl class="field-list">
            <?php foreach ($fields as $field): ?>
                <?php $shown = $field->display($values[$field->id] ?? null); ?>
                <div><dt dir="auto"><?= $this->e($field->name) ?></dt><dd dir="auto"><?= $shown !== '' ? $this->e($shown) : '<span class="muted">' . $this->te('Not set') . '</span>' ?></dd></div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <a class="btn btn-ghost btn-block" href="<?= $this->url('/boards/' . $board->publicId . '/fields') ?>"><?= $this->icon('edit') ?><?= $this->te('Manage the board’s fields') ?></a>
    <?php endif; ?>
</section>
