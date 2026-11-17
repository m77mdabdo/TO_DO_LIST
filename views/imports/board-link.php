<?php
/**
 * "Import" in the board toolbar (slot "board.toolbar"), for those who can add cards.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var ?\App\Modules\Access\Contract\BoardRole $role
 */
use App\Modules\Access\Contract\Permission;

if ($board->archived || $role === null || !$role->allows(Permission::EditCards)) {
    return;
}
?>
<a class="btn btn-ghost" href="<?= $this->url('/boards/' . $board->publicId . '/import') ?>" title="<?= $this->te('Import cards from a CSV file') ?>"><?= $this->icon('upload') ?><span class="hide-sm"><?= $this->te('Import') ?></span></a>
