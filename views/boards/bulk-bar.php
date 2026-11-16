<?php
/**
 * The bar for acting on many cards at once. The card checkboxes on the
 * tiles belong to this form through their form="bulk-form" attribute, so
 * it works without JavaScript; with it, only the field the chosen action
 * needs is shown and the count of chosen cards is kept up to date.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var list<\App\Modules\Board\Contract\ListSummary> $lists
 * @var list<\App\Modules\Label\Contract\Label> $boardLabels
 * @var list<int> $audience
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var bool $canEdit
 * @var bool $canMove
 * @var bool $canArchive
 * @var bool $canDelete
 */
$form = $this->form;
$actions = array_filter([
    'move'      => $canMove ? 'Move to list' : null,
    'complete'  => $canEdit ? 'Mark as done' : null,
    'reopen'    => $canEdit ? 'Mark as not done' : null,
    'assign'    => $canEdit ? 'Assign someone' : null,
    'unassign'  => $canEdit ? 'Take someone off' : null,
    'label'     => $canEdit && $boardLabels ? 'Add a label' : null,
    'unlabel'   => $canEdit && $boardLabels ? 'Remove a label' : null,
    'due'       => $canEdit ? 'Set the due date' : null,
    'clear_due' => $canEdit ? 'Remove the due date' : null,
    'archive'   => $canArchive ? 'Archive' : null,
    'delete'    => $canDelete ? 'Delete for good' : null,
]);
$chosen = (string) $form->old('action', 'move');
$error = $form->error('cards') ?? $form->error('action') ?? $form->error('confirm') ?? $form->error('value') ?? $form->error('list') ?? $form->error('member') ?? $form->error('card');
?>
<form id="bulk-form" class="bulk-bar js-bulk" action="<?= $this->url('/boards/' . $board->publicId . '/cards/bulk') ?>" method="post"
      aria-labelledby="bulk-title" data-confirm-delete="<?= $this->te('Delete the chosen cards for good? This cannot be undone.') ?>">
    <?= $this->csrfField() ?>
    <h2 id="bulk-title" class="visually-hidden"><?= $this->te('Act on the chosen cards') ?></h2>
    <p class="bulk-count" aria-live="polite"><span class="js-bulk-count">0</span> <?= $this->te('chosen') ?></p>

    <label><span><?= $this->te('Action') ?></span>
        <select name="action" class="js-bulk-action">
            <?php foreach ($actions as $value => $text): ?>
                <option value="<?= $value ?>" <?= $value === $chosen ? 'selected' : '' ?>><?= $this->te($text) ?></option>
            <?php endforeach; ?>
        </select></label>

    <?php if ($canMove): ?>
        <label data-bulk-for="move"><span><?= $this->te('List') ?></span>
            <select name="value_move">
                <?php foreach ($lists as $list): ?>
                    <option value="<?= $list->id ?>"><?= $this->e($list->name) ?></option>
                <?php endforeach; ?>
            </select></label>
    <?php endif; ?>
    <?php if ($canEdit): ?>
        <?php foreach (['assign' => 'Person', 'unassign' => 'Person'] as $action => $text): ?>
            <label data-bulk-for="<?= $action ?>"><span><?= $this->te($text) ?></span>
                <select name="value_<?= $action ?>">
                    <?php foreach ($audience as $id): if (!isset($people[$id])) continue; ?>
                        <option value="<?= $id ?>"><?= $this->e($people[$id]->name) ?></option>
                    <?php endforeach; ?>
                </select></label>
        <?php endforeach; ?>
        <?php if ($boardLabels): ?>
            <?php foreach (['label', 'unlabel'] as $action): ?>
                <label data-bulk-for="<?= $action ?>"><span><?= $this->te('Label') ?></span>
                    <select name="value_<?= $action ?>">
                        <?php foreach ($boardLabels as $label): ?>
                            <option value="<?= $label->id ?>"><?= $this->e($label->name) ?></option>
                        <?php endforeach; ?>
                    </select></label>
            <?php endforeach; ?>
        <?php endif; ?>
        <label data-bulk-for="due"><span><?= $this->te('Due date') ?></span>
            <input type="date" name="value_due"></label>
    <?php endif; ?>
    <?php if ($canDelete): ?>
        <label data-bulk-for="delete"><span><?= $this->te('Type the number of chosen cards to confirm') ?></span>
            <input type="number" name="confirm" min="1" max="200" inputmode="numeric"></label>
    <?php endif; ?>

    <button class="btn btn-primary" type="submit"><?= $this->te('Apply') ?></button>
    <button class="btn btn-ghost js-bulk-all" type="button" hidden><?= $this->te('Choose all shown') ?></button>
    <?php if ($error): ?>
        <p class="field-error" role="alert"><?= $this->te($error) ?></p>
    <?php endif; ?>
</form>
