<?php
/**
 * Watch toggle in the board bar (slot "board.actions").
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var bool $watching
 */
?>
<form action="<?= $this->url('/boards/' . $board->publicId . '/watch') ?>" method="post">
    <?= $this->csrfField() ?>
    <button class="btn <?= $watching ? 'btn-primary' : 'btn-ghost' ?>" type="submit" aria-pressed="<?= $watching ? 'true' : 'false' ?>"
            title="<?= $this->te($watching ? 'Stop watching this board' : 'Get notified about every card on this board') ?>">
        <?= $this->icon('eye') ?><span class="hide-sm"><?= $this->te($watching ? 'Watching' : 'Watch') ?></span>
    </button>
</form>
