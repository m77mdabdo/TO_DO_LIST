<?php
/**
 * Tabs for the views across boards.
 *
 * @var \App\View\View $this
 * @var ?\App\Modules\Workspace\Contract\WorkspaceSummary $currentWorkspace from the header composer
 * @var string $current "my-tasks", "cards" or "report"
 */
$ws = $workspace ?? $currentWorkspace ?? null;
$tabs = ['my-tasks' => ['/my-tasks', 'inbox', 'My tasks']];
if ($ws !== null) {
    $tabs['cards'] = ['/workspaces/' . $ws->publicId . '/cards', 'table', 'All cards'];
    $tabs['report'] = ['/workspaces/' . $ws->publicId . '/report', 'chart', 'Team report'];
}
?>
<div class="board-bar">
    <div class="board-bar-title">
        <h1 dir="auto"><?= $this->e($ws !== null && $current !== 'my-tasks' ? $ws->name : $this->t('My tasks')) ?></h1>
    </div>
    <nav class="view-tabs" aria-label="<?= $this->te('Views across boards') ?>">
        <?php foreach ($tabs as $key => [$path, $icon, $label]): ?>
            <a href="<?= $this->url($path) ?>" class="<?= $key === $current ? 'is-active' : '' ?>" <?= $key === $current ? 'aria-current="page"' : '' ?>><?= $this->icon($icon) ?><span><?= $this->te($label) ?></span></a>
        <?php endforeach; ?>
    </nav>
</div>
