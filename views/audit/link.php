<?php
/**
 * Link to the audit log on the members page (slot "workspace.members"), for owners and admins.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 */
if (!$workspace->role->managesAllBoards()) {
    return;
}
?>
<p class="center"><a class="btn btn-ghost" href="<?= $this->url('/workspaces/' . $workspace->publicId . '/audit') ?>"><?= $this->icon('shield') ?> <?= $this->te('Audit log') ?></a></p>
