<?php
/**
 * Board bar (slot "board.actions"): the board's buttons, and a link to its
 * automations for managers.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var bool $canManage
 * @var list<\App\Modules\Automation\Contract\Automation> $buttons
 */
if (!$canManage && $buttons === []) {
    return;
}
?>
<details class="menu menu-end automation-menu">
    <summary class="btn btn-ghost" title="<?= $this->te('Automation') ?>"><?= $this->icon('bolt') ?><span class="hide-sm"><?= $this->te('Automation') ?></span></summary>
    <div class="menu-panel">
        <?php foreach ($buttons as $button): ?>
            <form action="<?= $this->url('/automations/' . $button->publicId . '/run') ?>" method="post">
                <?= $this->csrfField() ?>
                <button class="menu-item" type="submit"><?= $this->icon('play') ?><span dir="auto"><?= $this->e($button->name) ?></span></button>
            </form>
        <?php endforeach; ?>
        <?php if ($buttons === []): ?>
            <p class="muted small menu-note"><?= $this->te('No board buttons yet.') ?></p>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <a class="menu-item" href="<?= $this->url('/boards/' . $board->publicId . '/automation') ?>"><?= $this->icon('bolt') ?><?= $this->te('Rules, buttons and schedules') ?></a>
        <?php endif; ?>
    </div>
</details>
