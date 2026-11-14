<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var list<array{userId: int, role: \App\Modules\Workspace\Contract\WorkspaceRole}> $members
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var int $viewerId
 * @var list<\App\Modules\Workspace\Contract\WorkspaceRole> $grantable roles the viewer may give
 */
$base = '/workspaces/' . $workspace->publicId;
$manages = $workspace->role->managesAllBoards();
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow stack">
    <section class="card stack">
        <div>
            <p class="eyebrow" dir="auto"><?= $this->e($workspace->companyName) ?></p>
            <h1><?= $this->te('Members') ?> <span class="muted">(<?= count($members) ?>)</span></h1>
        </div>
        <?= $this->partial('partials/field-error', ['field' => 'member']) ?>
        <?= $this->partial('partials/field-error', ['field' => 'role']) ?>

        <ul class="member-list">
            <?php foreach ($members as $member): $person = $people[$member['userId']] ?? null; ?>
                <?php if ($person === null) continue; ?>
                <?php $editable = $manages && $person->id !== $viewerId && $workspace->role->outranksForManagement($member['role']); ?>
                <li>
                    <span class="avatar" aria-hidden="true"><?= $this->e($person->initial()) ?></span>
                    <span class="member-name">
                        <strong><bdi><?= $this->e($person->name) ?></bdi><?php if ($person->id === $viewerId): ?> <small class="muted">(<?= $this->te('you') ?>)</small><?php endif; ?></strong>
                        <small class="muted"><bdi><?= $this->e($person->email) ?></bdi></small>
                    </span>
                    <?php if ($editable): ?>
                        <form action="<?= $this->url($base . '/members/' . $person->id) ?>" method="post" class="member-role">
                            <?= $this->csrfField() ?>
                            <label class="visually-hidden" for="role-<?= $person->id ?>"><?= $this->te('Role') ?></label>
                            <select name="role" id="role-<?= $person->id ?>">
                                <?php foreach ($grantable as $role): ?>
                                    <option value="<?= $role->value ?>" <?= $role === $member['role'] ? 'selected' : '' ?>><?= $this->te($role->label()) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-ghost" type="submit"><?= $this->te('Save') ?></button>
                        </form>
                        <form action="<?= $this->url($base . '/members/' . $person->id . '/remove') ?>" method="post"
                              data-confirm="<?= $this->te('Remove this person from the workspace? They lose access to all its boards.') ?>">
                            <?= $this->csrfField() ?>
                            <button class="icon-btn danger" type="submit" aria-label="<?= $this->te('Remove') ?>" title="<?= $this->te('Remove') ?>"><?= $this->icon('close') ?></button>
                        </form>
                    <?php else: ?>
                        <span class="pill"><?= $this->te($member['role']->label()) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <p class="muted small"><?= $this->te('Owners and admins manage every board. Members see the workspace boards. Guests only see the boards they were invited to.') ?></p>
    </section>

    <?= $this->slot('workspace.members', ['workspace' => $workspace, 'viewerId' => $viewerId]) ?>

    <form action="<?= $this->url($base . '/leave') ?>" method="post" class="leave-form"
          data-confirm="<?= $this->te('Leave this workspace? You will need a new invitation to come back.') ?>">
        <?= $this->csrfField() ?>
        <button class="link-btn danger" type="submit"><?= $this->te('Leave this workspace') ?></button>
    </form>
</main>
