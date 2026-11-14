<?php
/**
 * Link to the board's webhooks, in the automation menu (slot "board.automation-menu").
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 */
?>
<a class="menu-item" href="<?= $this->url('/boards/' . $board->publicId . '/webhooks') ?>"><?= $this->icon('plug') ?><?= $this->te('Webhooks') ?></a>
