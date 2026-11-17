<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var list<\App\Modules\Board\Contract\BoardSummary> $boards
 * @var list<\App\Modules\Board\Contract\BoardSummary> $archived
 * @var \App\Modules\Identity\Contract\User $user
 * @var string $greeting
 * @var list<\App\Modules\Board\Contract\BoardSummary> $templates boards saved as templates
 * @var array<string, array{name: string, description: string, lists: list<string>}> $builtIns
 * @var list<array{id: int, name: string}> $groups board groups of the workspace, in order
 * @var array<int, int> $boardGroup board id => group id
 * @var bool $canManageGroups
 */
use App\Modules\Board\Contract\BoardSummary;

$form = $this->form;
$groups ??= [];
$boardGroup ??= [];
$canManageGroups ??= false;
$groupIds = array_column($groups, 'id');
$inGroup = [];
$loose = [];
foreach ($boards as $board) {
    $groupId = $boardGroup[$board->id] ?? null;
    if ($groupId !== null && in_array($groupId, $groupIds, true)) {
        $inGroup[$groupId][] = $board;
    } else {
        $loose[] = $board;
    }
}
$tile = function (BoardSummary $board): string {
    ob_start(); ?>
            <a class="board-tile tile-<?= $this->e($board->color) ?>" href="<?= $this->url('/boards/' . $board->publicId) ?>">
                <strong dir="auto"><?= $this->e($board->name) ?></strong>
                <?php if ($board->isTemplate): ?><span class="tile-badge"><?= $this->icon('copy') ?><?= $this->te('Template') ?></span><?php endif; ?>
                <span class="tile-meta">
                    <?= $this->icon($board->visibility->value === 'private' ? 'lock' : 'users') ?>
                    <?= $this->te($board->visibility->value === 'private' ? 'Private' : 'Workspace') ?>
                </span>
            </a>
    <?php return (string) ob_get_clean();
};
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap">
    <section class="home-head">
        <div>
            <p class="eyebrow" dir="auto"><?= $this->e($workspace->companyName) ?></p>
            <h1><?= $this->te($greeting) ?><?= $this->te(',') ?> <span class="gradient-text" dir="auto"><?= $this->e($user->firstName()) ?></span></h1>
            <p class="muted"><?= $this->te('Pick a board or start a new one.') ?></p>
        </div>
    </section>

    <?php foreach ($groups as $i => $group): if (!isset($inGroup[$group['id']]) && !$canManageGroups) continue; ?>
        <section class="board-group" id="group-<?= $group['id'] ?>" aria-labelledby="group-title-<?= $group['id'] ?>">
            <div class="board-group-head">
                <h2 id="group-title-<?= $group['id'] ?>" dir="auto"><?= $this->e($group['name']) ?> <span class="count"><?= count($inGroup[$group['id']] ?? []) ?></span></h2>
                <?php if ($canManageGroups): ?>
                    <div class="row-actions">
                        <?php foreach (['1' => ['Move up', 'up', $i > 0], '0' => ['Move down', 'chevron', $i < count($groups) - 1]] as $up => [$label, $icon, $show]): if (!$show) continue; ?>
                            <form action="<?= $this->url('/board-groups/' . $group['id'] . '/move') ?>" method="post">
                                <?= $this->csrfField() ?><input type="hidden" name="up" value="<?= $up ?>">
                                <button class="icon-btn" type="submit" title="<?= $this->te($label) ?>" aria-label="<?= $this->te($label) ?>"><?= $this->icon($icon) ?></button>
                            </form>
                        <?php endforeach; ?>
                        <details class="menu menu-end">
                            <summary class="icon-btn" title="<?= $this->te('Group actions') ?>" aria-label="<?= $this->te('Group actions') ?>"><?= $this->icon('more') ?></summary>
                            <div class="menu-panel stack">
                                <form action="<?= $this->url('/board-groups/' . $group['id']) ?>" method="post" class="stack-sm">
                                    <?= $this->csrfField() ?>
                                    <label><span><?= $this->te('Group name') ?></span>
                                        <input name="name" required maxlength="80" dir="auto" value="<?= $this->e($group['name']) ?>"></label>
                                    <button class="btn btn-ghost" type="submit"><?= $this->te('Rename') ?></button>
                                </form>
                                <form action="<?= $this->url('/board-groups/' . $group['id'] . '/delete') ?>" method="post" data-confirm="<?= $this->te('Delete this group? Its boards stay and move to “Other boards”.') ?>">
                                    <?= $this->csrfField() ?>
                                    <button class="menu-item danger" type="submit"><?= $this->icon('trash') ?><?= $this->te('Delete group') ?></button>
                                </form>
                            </div>
                        </details>
                    </div>
                <?php endif; ?>
            </div>
            <div class="board-grid">
                <?php foreach ($inGroup[$group['id']] ?? [] as $board): ?><?= $tile($board) ?><?php endforeach; ?>
                <?php if (!isset($inGroup[$group['id']])): ?><p class="muted small"><?= $this->te('No boards in this group yet. Put a board here from its settings.') ?></p><?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if ($groups): ?><h2 class="board-group-other"><?= $this->te('Other boards') ?></h2><?php endif; ?>
    <section class="board-grid" aria-label="<?= $this->te($groups ? 'Other boards' : 'Boards') ?>">
        <?php foreach ($loose as $board): ?><?= $tile($board) ?><?php endforeach; ?>

        <?php if ($workspace->role->canCreateBoards()): ?>
            <details class="board-tile tile-new" <?= $form->error('name') ? 'open' : '' ?>>
                <summary><?= $this->icon('plus') ?> <?= $this->te('Create board') ?></summary>
                <form action="<?= $this->url('/boards') ?>" method="post" class="stack" novalidate>
                    <?= $this->csrfField() ?>
                    <label>
                        <span><?= $this->te('Start from') ?></span>
                        <select name="template">
                            <option value=""><?= $this->te('A blank board') ?></option>
                            <optgroup label="<?= $this->te('Ready-made templates') ?>">
                                <?php foreach ($builtIns as $key => $template): ?>
                                    <option value="builtin:<?= $this->e($key) ?>" <?= $form->old('template') === 'builtin:' . $key ? 'selected' : '' ?>><?= $this->te($template['name']) ?>: <?= $this->e(implode(' · ', array_map(fn ($l) => $this->t($l), $template['lists']))) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php if ($templates): ?>
                                <optgroup label="<?= $this->te('Your templates') ?>">
                                    <?php foreach ($templates as $template): ?>
                                        <option value="<?= $this->e($template->publicId) ?>" <?= $form->old('template') === $template->publicId ? 'selected' : '' ?>><?= $this->e($template->name) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </label>
                    <?php if ($templates): ?>
                        <label class="check"><input type="checkbox" name="with_cards" value="1"> <?= $this->te('Copy the cards of a saved template too') ?></label>
                    <?php endif; ?>
                    <label>
                        <span><?= $this->te('Board name') ?></span>
                        <input name="name" maxlength="100" dir="auto" placeholder="<?= $this->te('Leave empty to use the template name') ?>" value="<?= $this->e($form->old('name')) ?>"
                               aria-invalid="<?= $form->error('name') ? 'true' : 'false' ?>">
                        <?= $this->partial('partials/field-error', ['field' => 'name']) ?>
                    </label>
                    <fieldset class="swatches">
                        <legend><?= $this->te('Color') ?></legend>
                        <?php foreach (BoardSummary::COLORS as $i => $color): ?>
                            <label class="swatch tile-<?= $color ?>">
                                <input type="radio" name="color" value="<?= $color ?>" <?= $i === 0 ? 'checked' : '' ?>>
                                <span class="visually-hidden"><?= $this->e($color) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <label>
                        <span><?= $this->te('Visibility') ?></span>
                        <select name="visibility">
                            <option value="private"><?= $this->te('Private: only board members') ?></option>
                            <option value="workspace"><?= $this->te('Workspace: every member can view') ?></option>
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit"><?= $this->te('Create board') ?></button>
                </form>
            </details>
            <?= $this->slot('boards.create', ['workspace' => $workspace]) ?>
        <?php endif; ?>
    </section>

    <?php if ($canManageGroups): ?>
        <details class="board-groups-manage" id="board-groups" <?= $form->error('group_name') ? 'open' : '' ?>>
            <summary class="btn btn-ghost"><?= $this->icon('board') ?><?= $this->te('Board groups') ?></summary>
            <form action="<?= $this->url('/board-groups') ?>" method="post" class="inline-form">
                <?= $this->csrfField() ?>
                <input name="name" required maxlength="80" dir="auto" placeholder="<?= $this->te('e.g. Marketing') ?>" aria-label="<?= $this->te('Group name') ?>"
                       aria-invalid="<?= $form->error('group_name') ? 'true' : 'false' ?>">
                <button class="btn btn-primary" type="submit"><?= $this->icon('plus') ?><?= $this->te('Add group') ?></button>
            </form>
            <?= $this->partial('partials/field-error', ['field' => 'group_name']) ?>
            <p class="muted small"><?= $this->te('Groups sort the boards on this page for everyone in the workspace. Each person still sees only the boards they can open.') ?></p>
        </details>
    <?php endif; ?>

    <?php if (!$boards && !$workspace->role->canCreateBoards()): ?>
        <p class="muted center"><?= $this->te('No boards have been shared with you yet.') ?></p>
    <?php endif; ?>

    <?php if ($archived): ?>
        <details class="archived-boards">
            <summary><?= $this->icon('archive') ?> <?= $this->te('Archived boards') ?> (<?= count($archived) ?>)</summary>
            <ul>
                <?php foreach ($archived as $board): ?>
                    <li>
                        <span dir="auto"><?= $this->e($board->name) ?></span>
                        <a class="btn btn-ghost" href="<?= $this->url('/boards/' . $board->publicId) ?>"><?= $this->te('Open') ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>
</main>
