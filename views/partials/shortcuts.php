<?php
/**
 * The keyboard shortcuts, listed in a dialog. Every shortcut is a faster
 * way to reach something that also has a visible button or field.
 *
 * @var \App\View\View $this
 */
$shortcuts = [
    '/'   => 'Search (the board filter on a board page)',
    'F'   => 'Show or hide the board filters',
    'N'   => 'Add a card to the first list',
    'E'   => 'Edit the open card',
    'C'   => 'Comment on the open card',
    '?'   => 'Show these shortcuts',
    'Esc' => 'Close menus and dialogs, leave a field',
];
?>
<dialog class="shortcuts-dialog card" id="shortcuts" aria-labelledby="shortcuts-title">
    <div class="dialog-head">
        <h2 id="shortcuts-title"><?= $this->icon('keyboard') ?> <?= $this->te('Keyboard shortcuts') ?></h2>
        <form method="dialog"><button class="icon-btn" title="<?= $this->te('Close') ?>" aria-label="<?= $this->te('Close') ?>"><?= $this->icon('close') ?></button></form>
    </div>
    <dl class="shortcut-list">
        <?php foreach ($shortcuts as $key => $what): ?>
            <dt><kbd><?= $this->e($key) ?></kbd></dt>
            <dd><?= $this->te($what) ?></dd>
        <?php endforeach; ?>
    </dl>
</dialog>
