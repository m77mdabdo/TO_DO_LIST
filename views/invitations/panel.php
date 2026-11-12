<?php
/**
 * Invite form and open invitations on the members page (slot "workspace.members").
 *
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var bool $canInvite
 * @var list<\App\Modules\Invitation\Contract\Invitation> $invitations
 * @var array<int, \App\Modules\Identity\Contract\User> $inviters
 * @var list<\App\Modules\Board\Contract\BoardSummary> $boards
 * @var ?string $freshLink
 * @var DateTimeImmutable $now
 */
if (!$canInvite) {
    return;
}
$back = '/workspaces/' . $workspace->publicId . '/members';
$oldBoards = $this->form->oldArray('boards');
?>
<section class="card stack" id="invite">
    <h2 class="section-title"><?= $this->icon('mail') ?> <?= $this->te('Invite people') ?></h2>

    <?php if ($freshLink): ?>
        <div class="invite-link stack" role="status">
            <p class="small"><?= $this->te('We emailed the invitation. You can also copy this link and send it yourself. It is shown only now.') ?></p>
            <div class="copy-row">
                <input type="text" readonly value="<?= $this->e($freshLink) ?>" class="js-copy-source" dir="ltr" aria-label="<?= $this->te('Invitation link') ?>">
                <button type="button" class="btn btn-ghost js-copy" data-copied="<?= $this->te('Copied') ?>"><?= $this->icon('link') ?> <?= $this->te('Copy') ?></button>
            </div>
        </div>
    <?php endif; ?>

    <form action="<?= $this->url('/workspaces/' . $workspace->publicId . '/invitations') ?>" method="post" class="stack">
        <?= $this->csrfField() ?>
        <div class="field-row">
            <label><?= $this->te('Email') ?>
                <input type="email" name="email" required maxlength="190" dir="ltr" value="<?= $this->e($this->form->old('email')) ?>" placeholder="name@company.com">
                <?= $this->partial('partials/field-error', ['field' => 'email']) ?>
            </label>
            <label><?= $this->te('Workspace role') ?>
                <select name="role">
                    <?php foreach ($workspace->role->grantable() as $role): ?>
                        <?php if ($role === \App\Modules\Workspace\Contract\WorkspaceRole::Owner) continue; ?>
                        <option value="<?= $role->value ?>" <?= ($this->form->old('role') ?: 'member') === $role->value ? 'selected' : '' ?>><?= $this->te($role->label()) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $this->partial('partials/field-error', ['field' => 'role']) ?>
            </label>
        </div>

        <?php if ($boards): ?>
            <fieldset class="stack-sm">
                <legend><?= $this->te('Also add them to these boards') ?> <small class="muted"><?= $this->te('(required for guests)') ?></small></legend>
                <div class="check-grid">
                    <?php foreach ($boards as $board): ?>
                        <label class="check"><input type="checkbox" name="boards[]" value="<?= $board->id ?>" <?= in_array((string) $board->id, $oldBoards, true) ? 'checked' : '' ?>> <bdi><?= $this->e($board->name) ?></bdi></label>
                    <?php endforeach; ?>
                </div>
                <?= $this->partial('partials/field-error', ['field' => 'boards']) ?>
                <label><?= $this->te('Role on those boards') ?>
                    <select name="board_role">
                        <?php foreach (['member' => 'Member', 'viewer' => 'Viewer', 'manager' => 'Manager'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($this->form->old('board_role') ?: 'member') === $value ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $this->partial('partials/field-error', ['field' => 'board_role']) ?>
                </label>
            </fieldset>
        <?php endif; ?>

        <div><button class="btn btn-primary" type="submit"><?= $this->icon('mail') ?> <?= $this->te('Send invitation') ?></button></div>
        <p class="muted small"><?= $this->te('The link works once, only for that email address, and expires in 7 days.') ?></p>
    </form>
</section>

<section class="card stack" id="invitations">
    <h2 class="section-title"><?= $this->te('Open invitations') ?> <span class="muted">(<?= count($invitations) ?>)</span></h2>
    <?= $this->partial('partials/field-error', ['field' => 'invitation']) ?>
    <?php if (!$invitations): ?>
        <p class="muted small"><?= $this->te('No open invitations.') ?></p>
    <?php else: ?>
        <ul class="member-list">
            <?php foreach ($invitations as $invitation): ?>
                <?php $expired = !$invitation->isPending($now); ?>
                <li>
                    <span class="avatar avatar-muted" aria-hidden="true"><?= $this->icon('mail') ?></span>
                    <span class="member-name">
                        <strong><bdi><?= $this->e($invitation->email) ?></bdi></strong>
                        <small class="muted">
                            <?= $this->te($invitation->role->label()) ?>
                            <?php if ($invitation->boardIds): ?> · <?= $this->te(':count boards', [':count' => (string) count($invitation->boardIds)]) ?><?php endif; ?>
                            · <?= $this->te('invited by :name', [':name' => $inviters[$invitation->invitedBy]->name ?? '?']) ?>
                            · <?= $expired ? '<span class="danger">' . $this->te('expired') . '</span>' : $this->te('expires :date', [':date' => substr($invitation->expiresAt, 0, 10)]) ?>
                        </small>
                    </span>
                    <span class="row-actions">
                        <form action="<?= $this->url('/invitations/' . $invitation->publicId . '/resend') ?>" method="post">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="back" value="<?= $this->e($back) ?>">
                            <button class="btn btn-ghost" type="submit"><?= $this->te('Send again') ?></button>
                        </form>
                        <form action="<?= $this->url('/invitations/' . $invitation->publicId . '/revoke') ?>" method="post" data-confirm="<?= $this->te('Cancel this invitation? Its link will stop working.') ?>">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="back" value="<?= $this->e($back) ?>">
                            <button class="icon-btn danger" type="submit" aria-label="<?= $this->te('Cancel invitation') ?>" title="<?= $this->te('Cancel invitation') ?>"><?= $this->icon('close') ?></button>
                        </form>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
