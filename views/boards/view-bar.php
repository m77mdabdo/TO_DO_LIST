<?php
/**
 * Title bar of the table, calendar and dashboard pages.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Access\Contract\BoardRole $role
 * @var string $current
 * @var array<string, string> $query
 */
?>
<div class="board-bar">
    <div class="board-bar-title">
        <h1 dir="auto"><a href="<?= $this->url('/boards/' . $board->publicId) ?>"><?= $this->e($board->name) ?></a></h1>
        <span class="pill pill-role"><?= $this->te($role->label()) ?></span>
        <?php if ($board->archived): ?><span class="pill"><?= $this->icon('archive') ?><?= $this->te('Archived') ?></span><?php endif; ?>
    </div>
    <?= $this->partial('boards/view-tabs', ['board' => $board, 'current' => $current, 'query' => $query]) ?>
</div>
