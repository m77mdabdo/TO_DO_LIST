<?php
/**
 * Board bar button to the board's members (slot "board.actions").
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 */
?>
<a class="btn btn-ghost" href="<?= $this->url('/boards/' . $board->publicId . '/members') ?>" title="<?= $this->te('Board members') ?>"><?= $this->icon('users') ?><span class="hide-sm"><?= $this->te('Members') ?></span></a>
