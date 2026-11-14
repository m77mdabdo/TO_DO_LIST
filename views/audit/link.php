<?php
/**
 * Links to the audit log and the full export on the members page (slot "workspace.members"), for owners and admins.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 */
if (!$workspace->role->managesAllBoards()) {
    return;
}
?>
<p class="center workspace-tools">
    <a class="btn btn-ghost" href="<?= $this->url('/workspaces/' . $workspace->publicId . '/audit') ?>"><?= $this->icon('shield') ?> <?= $this->te('Audit log') ?></a>
    <a class="btn btn-ghost" href="<?= $this->url('/workspaces/' . $workspace->publicId . '/export.json') ?>"><?= $this->icon('download') ?> <?= $this->te('Export all boards (JSON)') ?></a>
</p>
