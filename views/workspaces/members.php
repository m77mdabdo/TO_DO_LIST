<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var list<array{userId: int, role: \App\Modules\Workspace\Contract\WorkspaceRole}> $members
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var int $viewerId
 * @var list<\App\Modules\Workspace\Contract\WorkspaceRole> $grantable roles the viewer may give
 * @var list<array{userId: int, role: \App\Modules\Workspace\Contract\WorkspaceRole, since: string}> $suspended
 * @var array<int, \App\Modules\Identity\Contract\User> $suspendedPeople
 * @var \App\Modules\Workspace\Contract\WorkspacePolicy $policy
 */
$base = '/workspaces/' . $workspace->publicId;
$manages = $workspace->role->managesAllBoards();
$isOwner = $workspace->role === \App\Modules\Workspace\Contract\WorkspaceRole::Owner;
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
                        <form action="<?= $this->url($base . '/members/' . $person->id . '/suspend') ?>" method="post"
                              data-confirm="<?= $this->te('Suspend this person? They keep their place but can\'t open anything in the workspace until you turn them back on.') ?>">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="on" value="1">
                            <button class="btn btn-danger-ghost" type="submit"><?= $this->te('Suspend') ?></button>
                        </form>
                    <?php else: ?>
                        <span class="pill"><?= $this->te($member['role']->label()) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <p class="muted small"><?= $this->te('Owners and admins manage every board. Members see the workspace boards. Guests only see the boards they were invited to.') ?></p>
    </section>

    <?php if ($manages && $suspended !== []): ?>
        <section class="card stack" id="suspended" aria-labelledby="suspended-title">
            <h2 id="suspended-title"><?= $this->te('Suspended members') ?> <span class="muted">(<?= count($suspended) ?>)</span></h2>
            <ul class="member-list">
                <?php foreach ($suspended as $member): $person = $suspendedPeople[$member['userId']] ?? null; ?>
                    <?php if ($person === null) continue; ?>
                    <li>
                        <span class="avatar" aria-hidden="true"><?= $this->e($person->initial()) ?></span>
                        <span class="member-name">
                            <strong><bdi><?= $this->e($person->name) ?></bdi></strong>
                            <small class="muted"><bdi><?= $this->e($person->email) ?></bdi> · <?= $this->te($member['role']->label()) ?></small>
                        </span>
                        <?php if ($workspace->role->outranksForManagement($member['role'])): ?>
                            <form action="<?= $this->url($base . '/members/' . $person->id . '/suspend') ?>" method="post">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="on" value="0">
                                <button class="btn btn-ghost" type="submit"><?= $this->te('Turn back on') ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($isOwner): ?>
        <section class="card stack" id="rules" aria-labelledby="rules-title">
            <h2 id="rules-title"><?= $this->te('Workspace rules') ?></h2>
            <form action="<?= $this->url($base . '/rules') ?>" method="post" class="stack-sm">
                <?= $this->csrfField() ?>
                <label class="check"><input type="checkbox" name="members_invite" value="1" <?= $policy->membersInvite ? 'checked' : '' ?>> <?= $this->te('Members may invite people too') ?></label>
                <label for="invite-role"><?= $this->te('A member\'s invitation gives the role') ?></label>
                <select name="invite_role" id="invite-role">
                    <option value="member" <?= $policy->inviteRole->value === 'member' ? 'selected' : '' ?>><?= $this->te('Member') ?></option>
                    <option value="guest" <?= $policy->inviteRole->value === 'guest' ? 'selected' : '' ?>><?= $this->te('Guest') ?></option>
                </select>
                <label class="check"><input type="checkbox" name="invite_approval" value="1" <?= $policy->inviteApproval ? 'checked' : '' ?>> <?= $this->te('An owner or admin approves a member\'s invitation before it is sent') ?></label>
                <label class="check"><input type="checkbox" name="public_links" value="1" <?= $policy->publicLinks ? 'checked' : '' ?>> <?= $this->te('Board managers may share a board read-only through a secret link') ?></label>
                <div class="form-actions"><button class="btn" type="submit"><?= $this->te('Save rules') ?></button></div>
            </form>
        </section>

        <?php $heirs = array_filter($members, fn ($m) => $m['userId'] !== $viewerId && $m['role'] !== \App\Modules\Workspace\Contract\WorkspaceRole::Guest && isset($people[$m['userId']])); ?>
        <section class="card stack" id="ownership" aria-labelledby="ownership-title">
            <h2 id="ownership-title"><?= $this->te('Hand over ownership') ?></h2>
            <p class="muted small"><?= $this->te('The person you choose becomes an owner and you become an admin.') ?></p>
            <?= $this->partial('partials/field-error', ['field' => 'owner']) ?>
            <?php if ($heirs === []): ?>
                <p class="muted"><?= $this->te('Invite a member first. Guests can\'t own a workspace.') ?></p>
            <?php else: ?>
                <form action="<?= $this->url($base . '/owner') ?>" method="post" class="stack-sm"
                      data-confirm="<?= $this->te('Hand over this workspace? You will become an admin.') ?>">
                    <?= $this->csrfField() ?>
                    <label for="new-owner"><?= $this->te('New owner') ?></label>
                    <select name="user_id" id="new-owner">
                        <?php foreach ($heirs as $m): ?>
                            <option value="<?= $m['userId'] ?>"><?= $this->e($people[$m['userId']]->name) ?> (<?= $this->e($people[$m['userId']]->email) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-actions"><button class="btn btn-danger-ghost" type="submit"><?= $this->te('Hand over') ?></button></div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?= $this->slot('workspace.members', ['workspace' => $workspace, 'viewerId' => $viewerId]) ?>

    <form action="<?= $this->url($base . '/leave') ?>" method="post" class="leave-form"
          data-confirm="<?= $this->te('Leave this workspace? You will need a new invitation to come back.') ?>">
        <?= $this->csrfField() ?>
        <button class="link-btn danger" type="submit"><?= $this->te('Leave this workspace') ?></button>
    </form>
</main>
