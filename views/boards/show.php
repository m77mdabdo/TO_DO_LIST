<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Access\Contract\BoardRole $role
 * @var list<\App\Modules\Board\Contract\ListSummary> $lists
 * @var array<int, list<\App\Modules\Card\Contract\CardSummary>> $cardsByList
 * @var array<int, list<\App\Modules\Label\Contract\Label>> $cardLabels
 * @var list<\App\Modules\Label\Contract\Label> $boardLabels
 * @var list<\App\Modules\Activity\Contract\ActivityEntry> $activity
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var array<int, list<int>> $cardMembers card id => assigned user ids
 * @var \DateTimeImmutable $today
 */
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardSummary;

$open = !$board->archived;
$canEdit = $open && $role->allows(Permission::EditCards);
$canMove = $open && $role->allows(Permission::MoveCards);
$canArchive = $open && $role->allows(Permission::ArchiveItems);
$canManage = $role->allows(Permission::ManageSettings);
$base = '/boards/' . $board->publicId;
$form = $this->form;
?>
<?= $this->partial('partials/app-header') ?>

<div class="board-bar">
    <div class="board-bar-title">
        <h1 dir="auto"><?= $this->e($board->name) ?></h1>
        <span class="pill"><?= $this->icon($board->visibility->value === 'private' ? 'lock' : 'users') ?><?= $this->te($board->visibility->value === 'private' ? 'Private' : 'Workspace') ?></span>
        <span class="pill pill-role"><?= $this->te($role->label()) ?></span>
    </div>
    <div class="board-bar-actions">
        <a class="btn btn-ghost" href="<?= $this->url($base . '/archived') ?>"><?= $this->icon('archive') ?><span class="hide-sm"><?= $this->te('Archived items') ?></span></a>
        <?php if ($role->allows(Permission::Export)): ?>
            <a class="btn btn-ghost" href="<?= $this->url($base . '/export') ?>" title="<?= $this->te('Download as CSV') ?>"><?= $this->icon('download') ?></a>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <details class="menu menu-end">
                <summary class="btn btn-ghost"><?= $this->icon('more') ?><span class="visually-hidden"><?= $this->te('Board settings') ?></span></summary>
                <div class="menu-panel menu-wide">
                    <?php if ($open): ?>
                        <form action="<?= $this->url($base) ?>" method="post" class="stack" novalidate>
                            <?= $this->csrfField() ?>
                            <label><span><?= $this->te('Board name') ?></span>
                                <input name="name" required maxlength="100" dir="auto" value="<?= $this->e($board->name) ?>"></label>
                            <label><span><?= $this->te('Description') ?></span>
                                <textarea name="description" rows="2" maxlength="2000" dir="auto"><?= $this->e($board->description) ?></textarea></label>
                            <label><span><?= $this->te('Visibility') ?></span>
                                <select name="visibility">
                                    <option value="private" <?= $board->visibility->value === 'private' ? 'selected' : '' ?>><?= $this->te('Private: only board members') ?></option>
                                    <option value="workspace" <?= $board->visibility->value === 'workspace' ? 'selected' : '' ?>><?= $this->te('Workspace: every member can view') ?></option>
                                </select></label>
                            <fieldset class="swatches">
                                <legend><?= $this->te('Color') ?></legend>
                                <?php foreach (BoardSummary::COLORS as $color): ?>
                                    <label class="swatch tile-<?= $color ?>">
                                        <input type="radio" name="color" value="<?= $color ?>" <?= $color === $board->color ? 'checked' : '' ?>>
                                        <span class="visually-hidden"><?= $this->e($color) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button>
                        </form>
                        <hr>
                        <form action="<?= $this->url($base . '/archive') ?>" method="post" data-confirm="<?= $this->te('Archive this board? Nobody can change it until it is restored.') ?>">
                            <?= $this->csrfField() ?>
                            <button class="menu-item danger" type="submit"><?= $this->icon('archive') ?><?= $this->te('Archive board') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </details>
        <?php endif; ?>
    </div>
</div>

<?php if (!$open): ?>
    <div class="banner">
        <?= $this->icon('archive') ?>
        <span><?= $this->te('This board is archived and read-only.') ?></span>
        <?php if ($canManage): ?>
            <form action="<?= $this->url($base . '/restore') ?>" method="post">
                <?= $this->csrfField() ?>
                <button class="btn btn-primary" type="submit"><?= $this->icon('restore') ?><?= $this->te('Restore board') ?></button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($board->description): ?>
    <p class="board-description muted" dir="auto"><?= nl2br($this->e($board->description)) ?></p>
<?php endif; ?>

<div class="board-layout">
    <main class="lists js-lists" data-board-move-base="<?= $this->url($base . '/lists') ?>">
        <?php foreach ($lists as $i => $list): $cards = $cardsByList[$list->id] ?? []; ?>
            <section class="list" data-list-id="<?= $list->id ?>" <?= $canMove ? 'draggable="true"' : '' ?>
                     data-move-url="<?= $this->url($base . '/lists/' . $list->id . '/move') ?>">
                <header class="list-head">
                    <h2 dir="auto"><?= $this->e($list->name) ?> <span class="count"><?= count($cards) ?></span></h2>
                    <?php if ($canEdit || $canArchive || $canMove): ?>
                        <details class="menu menu-end">
                            <summary class="icon-btn" title="<?= $this->te('List actions') ?>"><?= $this->icon('more') ?></summary>
                            <div class="menu-panel">
                                <?php if ($canEdit): ?>
                                    <form action="<?= $this->url($base . '/lists/' . $list->id) ?>" method="post" class="stack">
                                        <?= $this->csrfField() ?>
                                        <label><span><?= $this->te('List name') ?></span>
                                            <input name="name" required maxlength="100" dir="auto" value="<?= $this->e($list->name) ?>"></label>
                                        <button class="btn btn-ghost" type="submit"><?= $this->te('Rename') ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canMove && count($lists) > 1): ?>
                                    <form action="<?= $this->url($base . '/lists/' . $list->id . '/move') ?>" method="post" class="stack">
                                        <?= $this->csrfField() ?>
                                        <label><span><?= $this->te('Move after') ?></span>
                                            <select name="after_id">
                                                <option value=""><?= $this->te('(first)') ?></option>
                                                <?php foreach ($lists as $other): if ($other->id === $list->id) continue; ?>
                                                    <option value="<?= $other->id ?>" <?= ($lists[$i - 1] ?? null)?->id === $other->id ? 'selected' : '' ?>><?= $this->e($other->name) ?></option>
                                                <?php endforeach; ?>
                                            </select></label>
                                        <button class="btn btn-ghost" type="submit"><?= $this->te('Move') ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canArchive): ?>
                                    <form action="<?= $this->url($base . '/lists/' . $list->id . '/archive') ?>" method="post">
                                        <?= $this->csrfField() ?>
                                        <button class="menu-item danger" type="submit"><?= $this->icon('archive') ?><?= $this->te('Archive list') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </header>

                <div class="list-cards js-cards" data-list-id="<?= $list->id ?>">
                    <?php foreach ($cards as $card): ?>
                        <?= $this->partial('partials/card-tile', [
                            'card' => $card, 'labels' => $cardLabels[$card->id] ?? [], 'canMove' => $canMove, 'canEdit' => $canEdit,
                            'members' => array_values(array_filter(array_map(static fn (int $id) => $people[$id] ?? null, $cardMembers[$card->id] ?? []))),
                        ]) ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($canEdit): ?>
                    <form class="add-card" action="<?= $this->url($base . '/cards') ?>" method="post">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="list_id" value="<?= $list->id ?>">
                        <input name="title" required maxlength="200" dir="auto" placeholder="<?= $this->te('Add a card') ?>" aria-label="<?= $this->te('Add a card') ?>">
                        <button class="icon-btn" type="submit" title="<?= $this->te('Add card') ?>"><?= $this->icon('plus') ?></button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php if ($canEdit): ?>
            <section class="list list-new">
                <form action="<?= $this->url($base . '/lists') ?>" method="post" class="stack">
                    <?= $this->csrfField() ?>
                    <input name="name" required maxlength="100" dir="auto" placeholder="<?= $this->te('Add a list') ?>" aria-label="<?= $this->te('Add a list') ?>"
                           aria-invalid="<?= $form->error('name') ? 'true' : 'false' ?>">
                    <?= $this->partial('partials/field-error', ['field' => 'name']) ?>
                    <button class="btn btn-primary" type="submit"><?= $this->icon('plus') ?><?= $this->te('Add list') ?></button>
                </form>
            </section>
        <?php elseif (!$lists): ?>
            <p class="muted"><?= $this->te('This board has no lists yet.') ?></p>
        <?php endif; ?>
    </main>

    <?php if ($activity): ?>
        <aside class="activity card">
            <h2><?= $this->icon('activity') ?> <?= $this->te('Activity') ?></h2>
            <ul class="activity-list">
                <?php foreach ($activity as $entry): ?>
                    <?= $this->partial('partials/activity-item', ['entry' => $entry, 'people' => $people]) ?>
                <?php endforeach; ?>
            </ul>
        </aside>
    <?php endif; ?>
</div>
