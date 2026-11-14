<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var int $viewerId
 * @var bool $canManage
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var array<int, \App\Modules\Access\Contract\BoardRole> $explicit board members
 * @var array<int, \App\Modules\Access\Contract\BoardRole> $implicit people with a role from the workspace
 * @var list<int> $addable workspace members who aren't board members yet
 * @var array<int, \App\Modules\Workspace\Contract\WorkspaceRole> $workspaceRoles
 */
use App\Modules\Access\Contract\BoardRole;

$base = '/boards/' . $board->publicId;
$roles = BoardRole::cases();
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow stack">
    <a class="back-link" href="<?= $this->url($base) ?>"><?= $this->icon('left') ?> <span dir="auto"><?= $this->e($board->name) ?></span></a>

    <section class="card stack">
        <h1><?= $this->te('Board members') ?> <span class="muted">(<?= count($explicit) ?>)</span></h1>
        <?= $this->partial('partials/field-error', ['field' => 'user_id']) ?>
        <?= $this->partial('partials/field-error', ['field' => 'role']) ?>

        <ul class="member-list">
            <?php foreach ($explicit as $userId => $role): $person = $people[$userId] ?? null; ?>
                <?php if ($person === null) continue; ?>
                <li>
                    <span class="avatar" aria-hidden="true"><?= $this->e($person->initial()) ?></span>
                    <span class="member-name">
                        <strong><bdi><?= $this->e($person->name) ?></bdi><?php if ($userId === $viewerId): ?> <small class="muted">(<?= $this->te('you') ?>)</small><?php endif; ?></strong>
                        <small class="muted"><?= isset($workspaceRoles[$userId]) ? $this->te($workspaceRoles[$userId]->label()) : '' ?></small>
                    </span>
                    <?php if ($canManage && $userId !== $viewerId): ?>
                        <form action="<?= $this->url($base . '/members') ?>" method="post" class="member-role">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                            <label class="visually-hidden" for="board-role-<?= $userId ?>"><?= $this->te('Role') ?></label>
                            <select name="role" id="board-role-<?= $userId ?>">
                                <?php foreach ($roles as $option): ?>
                                    <option value="<?= $option->value ?>" <?= $option === $role ? 'selected' : '' ?>><?= $this->te($option->label()) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-ghost" type="submit"><?= $this->te('Save') ?></button>
                        </form>
                        <form action="<?= $this->url($base . '/members/' . $userId . '/remove') ?>" method="post" data-confirm="<?= $this->te('Take this person off the board?') ?>">
                            <?= $this->csrfField() ?>
                            <button class="icon-btn danger" type="submit" aria-label="<?= $this->te('Remove') ?>" title="<?= $this->te('Remove') ?>"><?= $this->icon('close') ?></button>
                        </form>
                    <?php else: ?>
                        <span class="pill"><?= $this->te($role->label()) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($canManage && $addable): ?>
            <form action="<?= $this->url($base . '/members') ?>" method="post" class="field-row">
                <?= $this->csrfField() ?>
                <label><?= $this->te('Add someone from the workspace') ?>
                    <select name="user_id" required>
                        <?php foreach ($addable as $userId): if (!isset($people[$userId])) continue; ?>
                            <option value="<?= $userId ?>"><?= $this->e($people[$userId]->name) ?> (<?= $this->te($workspaceRoles[$userId]->label()) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?= $this->te('Role') ?>
                    <select name="role">
                        <?php foreach ($roles as $option): ?>
                            <option value="<?= $option->value ?>" <?= $option === BoardRole::Member ? 'selected' : '' ?>><?= $this->te($option->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="field-row-end"><button class="btn btn-primary" type="submit"><?= $this->icon('plus') ?> <?= $this->te('Add') ?></button></div>
            </form>
            <p class="muted small"><?= $this->te('Someone from outside the company? Invite them from the workspace members page.') ?></p>
        <?php endif; ?>
    </section>

    <?php if ($implicit): ?>
        <section class="card stack">
            <h2 class="section-title"><?= $this->te('Also on this board through the workspace') ?></h2>
            <ul class="member-list">
                <?php foreach ($implicit as $userId => $role): $person = $people[$userId] ?? null; ?>
                    <?php if ($person === null) continue; ?>
                    <li>
                        <span class="avatar" aria-hidden="true"><?= $this->e($person->initial()) ?></span>
                        <span class="member-name">
                            <strong><bdi><?= $this->e($person->name) ?></bdi></strong>
                            <small class="muted"><?= isset($workspaceRoles[$userId]) ? $this->te($workspaceRoles[$userId]->label()) : '' ?></small>
                        </span>
                        <span class="pill"><?= $this->te($role->label()) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>
