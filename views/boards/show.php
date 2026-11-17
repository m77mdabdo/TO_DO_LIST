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
 * @var array<int, list<array{icon: string, text: string, class: string, title: string}>> $cardBadges added by other modules
 * @var \DateTimeImmutable $today
 * @var \App\Modules\Board\Contract\CardDisplay $display
 * @var list<\App\Modules\Card\Contract\CardSummary> $templates template cards on this board
 * @var array<int, string> $cardCovers card id => cover picture path, added by the attachment module
 * @var string|null $boardPicture path of the board's picture, added by the attachment module
 * @var array<int, list<array{field: \App\Modules\Field\Contract\CustomField, text: string}>> $cardFields card id => custom fields shown on the tile
 * @var array<int, \App\Modules\Board\Contract\BoardSummary> $linkedBoards boards that board-link cards point at, only openable ones
 * @var list<int> $collapsed lists this person folded away
 * @var list<array{id: int, name: string}> $groups board groups of the workspace
 * @var int|null $boardGroupId
 */
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\CardDisplay;

$open = !$board->archived;
$cardBadges ??= [];
$cardCovers ??= [];
$cardFields ??= [];
$linkedBoards ??= [];
$boardPicture ??= null;
$collapsed ??= [];
$groups ??= [];
$boardGroupId ??= null;
$canEdit = $open && $role->allows(Permission::EditCards);
$canMove = $open && $role->allows(Permission::MoveCards);
$canArchive = $open && $role->allows(Permission::ArchiveItems);
$canManage = $role->allows(Permission::ManageSettings);
$base = '/boards/' . $board->publicId;
$form = $this->form;
$templates ??= [];
$selecting = ($selecting ?? false) && ($canEdit || $canMove || $canArchive);
?>
<?= $this->partial('partials/app-header') ?>
<?php if ($boardPicture !== null): ?>
    <img class="board-picture" src="<?= $this->url($boardPicture) ?>" alt="" aria-hidden="true">
<?php endif; ?>

<div class="board-bar">
    <div class="board-bar-title">
        <h1 dir="auto"><?= $this->e($board->name) ?></h1>
        <span class="pill"><?= $this->icon($board->visibility->value === 'private' ? 'lock' : 'users') ?><?= $this->te($board->visibility->value === 'private' ? 'Private' : 'Workspace') ?></span>
        <span class="pill pill-role"><?= $this->te($role->label()) ?></span>
    </div>
    <?= $this->partial('boards/view-tabs', ['board' => $board, 'current' => '', 'query' => $query ?? []]) ?>
    <div class="board-bar-actions">
        <?= $this->slot('board.actions', ['board' => $board, 'role' => $role]) ?>
        <details class="menu menu-end">
            <summary class="btn btn-ghost" title="<?= $this->te('Card display') ?>"><?= $this->icon('board') ?><span class="visually-hidden"><?= $this->te('Card display') ?></span></summary>
            <div class="menu-panel">
                <form action="<?= $this->url($base . '/display') ?>" method="post" class="stack">
                    <?= $this->csrfField() ?>
                    <fieldset class="stack-sm">
                        <legend><?= $this->te('Show on the cards') ?></legend>
                        <?php foreach (CardDisplay::PARTS as $part => $text): ?>
                            <label class="check"><input type="checkbox" name="show[]" value="<?= $part ?>" <?= $display->shows($part) ? 'checked' : '' ?>> <?= $this->te($text) ?></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="muted small"><?= $this->te('Only you see this choice.') ?></p>
                    <button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button>
                </form>
            </div>
        </details>
        <a class="btn btn-ghost" href="<?= $this->url($base . '/fields') ?>" title="<?= $this->te('Custom fields') ?>"><?= $this->icon('table') ?><span class="hide-sm"><?= $this->te('Fields') ?></span></a>
        <a class="btn btn-ghost" href="<?= $this->url($base . '/archived') ?>"><?= $this->icon('archive') ?><span class="hide-sm"><?= $this->te('Archived items') ?></span></a>
        <?= $this->slot('board.toolbar', ['board' => $board, 'role' => $role]) ?>
        <?php if ($role->allows(Permission::Export)): ?>
            <details class="menu menu-end">
                <summary class="btn btn-ghost" title="<?= $this->te('Export') ?>"><?= $this->icon('download') ?><span class="visually-hidden"><?= $this->te('Export') ?></span></summary>
                <div class="menu-panel">
                    <a class="menu-item" href="<?= $this->url($base . '/export') ?>"><?= $this->icon('table') ?><?= $this->te('Spreadsheet (CSV)') ?></a>
                    <a class="menu-item" href="<?= $this->url($base . '/export.json') ?>"><?= $this->icon('download') ?><?= $this->te('Everything (JSON)') ?></a>
                </div>
            </details>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <details class="menu menu-end" id="board-settings" <?= $form->error('background_picture') || $form->error('background') ? 'open' : '' ?>>
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
                            <?php $choices = ['off' => 'Nobody', 'members' => 'Board members (not viewers)', 'everyone' => 'Everyone who can see the board']; ?>
                            <div class="field-row">
                                <label><span><?= $this->te('Who can comment') ?></span>
                                    <select name="commenting">
                                        <?php foreach ($choices as $value => $text): ?>
                                            <option value="<?= $value ?>" <?= $board->commenting->value === $value ? 'selected' : '' ?>><?= $this->te($text) ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                <label><span><?= $this->te('Who can vote') ?></span>
                                    <select name="voting">
                                        <?php foreach ($choices as $value => $text): ?>
                                            <option value="<?= $value ?>" <?= $board->voting->value === $value ? 'selected' : '' ?>><?= $this->te($text) ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                            </div>
                            <fieldset class="swatches">
                                <legend><?= $this->te('Color') ?></legend>
                                <?php foreach (BoardSummary::COLORS as $color): ?>
                                    <label class="swatch tile-<?= $color ?>">
                                        <input type="radio" name="color" value="<?= $color ?>" <?= $color === $board->color ? 'checked' : '' ?>>
                                        <span class="visually-hidden"><?= $this->e($color) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <label><span><?= $this->te('Background') ?></span>
                                <select name="background">
                                    <?php foreach (BoardSummary::BACKGROUNDS as $value => $text): ?>
                                        <option value="<?= $value ?>" <?= $board->background === $value ? 'selected' : '' ?>><?= $this->te($text) ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                            <?= $this->partial('partials/field-error', ['field' => 'background']) ?>
                            <button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button>
                        </form>
                        <?php if ($groups): ?>
                            <hr>
                            <form action="<?= $this->url($base . '/group') ?>" method="post" class="stack-sm">
                                <?= $this->csrfField() ?>
                                <label><span><?= $this->te('Board group') ?></span>
                                    <select name="group">
                                        <option value=""><?= $this->te('No group') ?></option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?= $group['id'] ?>" <?= $boardGroupId === $group['id'] ? 'selected' : '' ?>><?= $this->e($group['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                <div><button class="btn btn-ghost" type="submit"><?= $this->te('Save') ?></button></div>
                            </form>
                        <?php endif; ?>
                        <hr>
                        <form action="<?= $this->url($base . '/background') ?>" method="post" enctype="multipart/form-data" class="stack-sm">
                            <?= $this->csrfField() ?>
                            <label><span><?= $this->te('Background picture') ?></span>
                                <input type="file" name="background_picture" accept=".jpg,.jpeg,.png,.gif,.webp" required
                                       aria-describedby="background-help" aria-invalid="<?= $form->error('background_picture') ? 'true' : 'false' ?>"></label>
                            <small class="muted" id="background-help"><?= $this->te('JPEG, PNG, GIF or WebP, up to 5 MB. Everyone who can see the board sees it.') ?></small>
                            <?= $this->partial('partials/field-error', ['field' => 'background_picture']) ?>
                            <div class="field-row">
                                <button class="btn btn-ghost" type="submit"><?= $this->icon('upload') ?><?= $this->te('Upload picture') ?></button>
                            </div>
                        </form>
                        <?php if ($boardPicture !== null): ?>
                            <form action="<?= $this->url($base . '/background/remove') ?>" method="post">
                                <?= $this->csrfField() ?>
                                <button class="menu-item" type="submit"><?= $this->icon('trash') ?><?= $this->te('Remove the picture') ?></button>
                            </form>
                        <?php endif; ?>
                        <hr>
                        <form action="<?= $this->url($base . '/template') ?>" method="post">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="on" value="<?= $board->isTemplate ? '0' : '1' ?>">
                            <button class="menu-item" type="submit"><?= $this->icon('copy') ?><?= $this->te($board->isTemplate ? 'Stop using as a template' : 'Make this board a template') ?></button>
                        </form>
                        <details class="copy-board">
                            <summary class="menu-item"><?= $this->icon('copy') ?><?= $this->te('Copy board') ?></summary>
                            <form action="<?= $this->url($base . '/copy') ?>" method="post" class="stack">
                                <?= $this->csrfField() ?>
                                <label><span><?= $this->te('Name of the copy') ?></span>
                                    <input name="name" required maxlength="100" dir="auto" value="<?= $this->e($this->t(':name (copy)', ['name' => $board->name])) ?>"></label>
                                <label class="check"><input type="checkbox" name="with_cards" value="1" checked> <?= $this->te('Copy the cards, with their labels and checklists') ?></label>
                                <label class="check"><input type="checkbox" name="with_members" value="1"> <?= $this->te('Add the same members') ?></label>
                                <p class="muted small"><?= $this->te('Comments, attachments and history are not copied.') ?></p>
                                <button class="btn btn-primary" type="submit"><?= $this->te('Copy board') ?></button>
                            </form>
                        </details>
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

<div class="board-tools">
    <?= $this->partial('boards/filters', compact('board', 'filter', 'boardLabels', 'audience', 'people', 'totalCards', 'shownCards')) ?>
    <?php if ($canEdit || $canMove || $canArchive): ?>
        <?php $selectQuery = $query ?? []; ?>
        <a class="btn btn-ghost <?= $selecting ? 'is-active' : '' ?>" aria-pressed="<?= $selecting ? 'true' : 'false' ?>"
           href="<?= $this->url($base . (($q = http_build_query($selecting ? $selectQuery : $selectQuery + ['select' => '1'])) !== '' ? '?' . $q : '')) ?>">
            <?= $this->icon('checklist') ?><?= $this->te($selecting ? 'Stop selecting' : 'Select cards') ?></a>
    <?php endif; ?>
</div>

<?php if ($selecting): ?>
    <?= $this->partial('boards/bulk-bar', compact('board', 'lists', 'boardLabels', 'audience', 'people', 'canEdit', 'canMove', 'canArchive') + ['canDelete' => $role->allows(Permission::DeleteItems) && $canArchive]) ?>
<?php endif; ?>

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

<div class="banner banner-updates js-board-stale" role="status" hidden>
    <?= $this->icon('activity') ?>
    <span class="js-board-stale-text"><?= $this->te('Someone changed this board.') ?></span>
    <button class="btn btn-primary js-board-refresh" type="button"><?= $this->te('Show the changes') ?></button>
</div>

<div class="board-layout">
    <main class="lists js-lists" data-board-move-base="<?= $this->url($base . '/lists') ?>"
          data-version="<?= (int) ($version ?? 0) ?>" data-changes-url="<?= $this->url($base . '/changes') ?>"
          data-updated-by="<?= $this->te(':name changed the board') ?>" data-updated="<?= $this->te('The board was updated') ?>">
        <?php foreach ($lists as $i => $list): $cards = $cardsByList[$list->id] ?? []; $isCollapsed = in_array($list->id, $collapsed, true); ?>
            <section class="list <?= $list->color ? 'list-color tile-' . $this->e($list->color) : '' ?> <?= $isCollapsed ? 'is-collapsed' : '' ?>" id="list-<?= $list->id ?>" data-list-id="<?= $list->id ?>" <?= $canMove ? 'draggable="true"' : '' ?>
                     data-move-url="<?= $this->url($base . '/lists/' . $list->id . '/move') ?>">
                <header class="list-head">
                    <h2 dir="auto"><?= $this->e($list->name) ?> <span class="count"><?= count($cards) ?></span></h2>
                    <form action="<?= $this->url($base . '/lists/' . $list->id . '/collapse') ?>" method="post" class="list-collapse">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="collapsed" value="<?= $isCollapsed ? '0' : '1' ?>">
                        <button class="icon-btn" type="submit" aria-expanded="<?= $isCollapsed ? 'false' : 'true' ?>"
                                title="<?= $isCollapsed ? $this->te('Expand list') : $this->te('Collapse list') ?>"
                                aria-label="<?= $isCollapsed ? $this->te('Expand list') : $this->te('Collapse list') ?>"><?= $this->icon($isCollapsed ? 'chevron' : 'up') ?></button>
                    </form>
                    <?php if (!$isCollapsed): ?>
                    <?php if ($canEdit || $canArchive || $canMove): ?>
                        <details class="menu menu-end">
                            <summary class="icon-btn" title="<?= $this->te('List actions') ?>"><?= $this->icon('more') ?></summary>
                            <div class="menu-panel">
                                <?php if ($canEdit): ?>
                                    <form action="<?= $this->url($base . '/lists/' . $list->id) ?>" method="post" class="stack">
                                        <?= $this->csrfField() ?>
                                        <label><span><?= $this->te('List name') ?></span>
                                            <input name="name" required maxlength="100" dir="auto" value="<?= $this->e($list->name) ?>"></label>
                                        <fieldset class="swatches swatches-sm">
                                            <legend><?= $this->te('List color') ?></legend>
                                            <label class="swatch swatch-none" title="<?= $this->te('No color') ?>">
                                                <input type="radio" name="color" value="" <?= $list->color === null ? 'checked' : '' ?>>
                                                <span class="visually-hidden"><?= $this->te('No color') ?></span>
                                            </label>
                                            <?php foreach (BoardSummary::COLORS as $color): ?>
                                                <label class="swatch tile-<?= $color ?>" title="<?= $this->te(ucfirst($color)) ?>">
                                                    <input type="radio" name="color" value="<?= $color ?>" <?= $color === $list->color ? 'checked' : '' ?>>
                                                    <span class="visually-hidden"><?= $this->te(ucfirst($color)) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </fieldset>
                                        <button class="btn btn-ghost" type="submit"><?= $this->te('Save') ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canEdit && $templates): ?>
                                    <form action="<?= $this->url($base . '/cards') ?>" method="post" class="stack">
                                        <?= $this->csrfField() ?>
                                        <input type="hidden" name="list_id" value="<?= $list->id ?>">
                                        <label><span><?= $this->te('New card from a template') ?></span>
                                            <select name="template">
                                                <?php foreach ($templates as $template): ?>
                                                    <option value="<?= $this->e($template->publicId) ?>"><?= $this->e($template->title) ?></option>
                                                <?php endforeach; ?>
                                            </select></label>
                                        <input name="title" maxlength="200" dir="auto" placeholder="<?= $this->te('Title (optional)') ?>" aria-label="<?= $this->te('Title (optional)') ?>">
                                        <button class="btn btn-ghost" type="submit"><?= $this->icon('copy') ?><?= $this->te('Create card') ?></button>
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
                                <?php if ($canMove && count($cards) > 1): ?>
                                    <form action="<?= $this->url($base . '/lists/' . $list->id . '/sort') ?>" method="post" class="stack">
                                        <?= $this->csrfField() ?>
                                        <div class="field-row">
                                            <label><span><?= $this->te('Sort cards by') ?></span>
                                                <select name="by">
                                                    <?php foreach (['due' => 'Due date', 'created' => 'Date created', 'name' => 'Name'] as $value => $text): ?>
                                                        <option value="<?= $value ?>"><?= $this->te($text) ?></option>
                                                    <?php endforeach; ?>
                                                </select></label>
                                            <label><span><?= $this->te('Order') ?></span>
                                                <select name="dir">
                                                    <option value="asc"><?= $this->te('Ascending (earliest, A to Z)') ?></option>
                                                    <option value="desc"><?= $this->te('Descending (latest, Z to A)') ?></option>
                                                </select></label>
                                        </div>
                                        <small class="muted"><?= $this->te('Sorts once, for everyone. Cards without a due date go last.') ?></small>
                                        <button class="btn btn-ghost" type="submit"><?= $this->te('Sort') ?></button>
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
                    <?php endif; ?>
                </header>
                <?php if (!$isCollapsed): ?>
                <div class="list-cards js-cards" data-list-id="<?= $list->id ?>">
                    <?php foreach ($cards as $card): ?>
                        <?= $this->partial('partials/card-tile', [
                            'card' => $card, 'labels' => $cardLabels[$card->id] ?? [], 'canMove' => $canMove, 'canEdit' => $canEdit,
                            'badges' => $cardBadges[$card->id] ?? [], 'display' => $display, 'selecting' => $selecting,
                            'cover' => $cardCovers[$card->id] ?? null, 'fields' => $cardFields[$card->id] ?? [],
                            'linkedBoard' => $card->linkBoardId !== null ? ($linkedBoards[$card->linkBoardId] ?? null) : null,
                            'members' => array_values(array_filter(array_map(static fn (int $id) => $people[$id] ?? null, $cardMembers[$card->id] ?? []))),
                        ]) ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($canEdit): ?>
                    <form class="add-card" action="<?= $this->url($base . '/cards') ?>" method="post">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="list_id" value="<?= $list->id ?>">
                        <input name="title" required maxlength="200" dir="auto" class="js-add-card" placeholder="<?= $this->te('Add a card') ?>" aria-label="<?= $this->te('Add a card') ?>">
                        <button class="icon-btn" type="submit" title="<?= $this->te('Add card') ?>"><?= $this->icon('plus') ?></button>
                    </form>
                <?php endif; ?>
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
        <aside class="activity card js-board-activity">
            <h2><?= $this->icon('activity') ?> <?= $this->te('Activity') ?></h2>
            <ul class="activity-list">
                <?php foreach ($activity as $entry): ?>
                    <?= $this->partial('partials/activity-item', ['entry' => $entry, 'people' => $people]) ?>
                <?php endforeach; ?>
            </ul>
            <a class="btn btn-ghost btn-block" href="<?= $this->url('/boards/' . $board->publicId . '/activity') ?>"><?= $this->te('All activity') ?></a>
        </aside>
    <?php endif; ?>
</div>
