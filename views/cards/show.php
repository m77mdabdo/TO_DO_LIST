<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Access\Contract\BoardRole $role
 * @var list<\App\Modules\Board\Contract\ListSummary> $lists
 * @var list<\App\Modules\Label\Contract\Label> $boardLabels
 * @var list<\App\Modules\Label\Contract\Label> $cardLabels
 * @var list<\App\Modules\Activity\Contract\ActivityEntry> $activity
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var array<int, \App\Modules\Access\Contract\BoardRole> $audience user id => role on the board
 * @var list<int> $assigned
 * @var array<string, string> $query
 * @var bool $moreActivity
 */
use App\Modules\Access\Contract\Permission;
use App\Modules\Label\Contract\Label;

$open = !$board->archived && !$card->archived;
$canEdit = $open && $role->allows(Permission::EditCards);
$canMove = $open && $role->allows(Permission::MoveCards);
$canArchive = !$board->archived && $role->allows(Permission::ArchiveItems);
$canLabels = !$board->archived && $role->allows(Permission::ManageLabels);
$attached = array_flip(array_map(static fn (Label $l) => $l->id, $cardLabels));
$listName = '';
foreach ($lists as $list) {
    if ($list->id === $card->listId) {
        $listName = $list->name;
    }
}
$form = $this->form;
$cardPath = '/cards/' . $card->publicId;
$editErrors = $form->error('title') || $form->error('description') || $form->error('start_date') || $form->error('due_date');
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap card-page">
    <a class="back-link" href="<?= $this->url('/boards/' . $board->publicId) ?>"><?= $this->icon('left') ?> <span dir="auto"><?= $this->e($board->name) ?></span></a>

    <div class="card-layout">
        <section class="card stack card-main">
            <p class="eyebrow"><?= $this->te('In list') ?> <span dir="auto"><?= $this->e($listName) ?></span>
                <?php if ($card->archived): ?><span class="pill"><?= $this->icon('archive') ?><?= $this->te('Archived') ?></span><?php endif; ?>
                <?php if ($card->isComplete()): ?><span class="pill pill-done"><?= $this->icon('check') ?><?= $this->te('Done') ?></span><?php endif; ?>
            </p>

            <?php if (!$canEdit || !$editErrors): ?>
                <h1 class="card-heading" dir="auto"><?= $this->e($card->title) ?></h1>
                <?php if ($card->startDate() || $card->dueDate()): ?>
                    <p class="date-range">
                        <?php if ($card->startDate()): ?><span class="due"><?= $this->te('Starts') ?> <?= $this->e($card->startDate()) ?></span><?php endif; ?>
                        <?php if ($card->dueDate()): ?><span class="due"><?= $this->icon('calendar') ?><?= $this->e($card->dueDate()) ?></span><?php endif; ?>
                    </p>
                <?php endif; ?>
                <?php if ($card->description): ?>
                    <div class="prose" dir="auto"><?= $this->markdown($card->description) ?></div>
                <?php elseif (!$canEdit): ?>
                    <p class="muted"><?= $this->te('No description.') ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($canEdit): ?>
                <details class="edit-card js-edit-card" <?= $editErrors || !$card->description ? 'open' : '' ?>>
                    <summary class="btn btn-ghost"><?= $this->icon('edit') ?><?= $this->te('Edit card') ?></summary>
                    <form action="<?= $this->url($cardPath) ?>" method="post" class="stack" novalidate>
                        <?= $this->csrfField() ?>
                        <label><span><?= $this->te('Title') ?></span>
                            <input name="title" required maxlength="200" dir="auto" value="<?= $this->e($form->old('title', $card->title)) ?>"
                                   aria-invalid="<?= $form->error('title') ? 'true' : 'false' ?>">
                            <?= $this->partial('partials/field-error', ['field' => 'title']) ?></label>
                        <label><span><?= $this->te('Description') ?></span>
                            <textarea name="description" rows="7" maxlength="10000" dir="auto" class="js-description"><?= $this->e($form->old('description', (string) $card->description)) ?></textarea>
                            <small class="muted"><?= $this->te('Markdown works: **bold**, *italic*, - lists, [links](https://…), `code`.') ?></small>
                            <?= $this->partial('partials/field-error', ['field' => 'description']) ?></label>
                        <div class="field-row">
                            <label><span><?= $this->te('Start date') ?></span>
                                <input type="date" name="start_date" value="<?= $this->e($form->old('start_date', (string) $card->startDate())) ?>"
                                       aria-invalid="<?= $form->error('start_date') ? 'true' : 'false' ?>">
                                <?= $this->partial('partials/field-error', ['field' => 'start_date']) ?></label>
                            <label><span><?= $this->te('Due date') ?></span>
                                <input type="date" name="due_date" value="<?= $this->e($form->old('due_date', (string) $card->dueDate())) ?>">
                                <?= $this->partial('partials/field-error', ['field' => 'due_date']) ?></label>
                        </div>
                        <div><button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button></div>
                    </form>
                </details>
            <?php endif; ?>

            <?= $this->slot('card.main', ['card' => $card, 'board' => $board, 'role' => $role, 'query' => $query]) ?>

            <?php if ($activity): ?>
                <div id="activity">
                    <h2 class="section-title"><?= $this->icon('activity') ?> <?= $this->te('Activity') ?></h2>
                    <ul class="activity-list">
                        <?php foreach ($activity as $entry): ?>
                            <?= $this->partial('partials/activity-item', ['entry' => $entry, 'people' => $people]) ?>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($moreActivity): ?>
                        <a class="btn btn-ghost" href="<?= $this->url($cardPath . '?activity_before=' . end($activity)->id . '#activity') ?>"><?= $this->te('Older activity') ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <aside class="card-side stack">
            <?= $this->slot('card.side', ['card' => $card, 'board' => $board, 'role' => $role]) ?>

            <section class="card stack">
                <h2 class="section-title"><?= $this->icon('users') ?> <?= $this->te('Members') ?></h2>
                <?php if ($assigned): ?>
                    <ul class="people-list">
                        <?php foreach ($assigned as $memberId): ?>
                            <?php $person = $people[$memberId] ?? null; if ($person === null) continue; ?>
                            <li>
                                <span class="avatar avatar-sm" aria-hidden="true"><?= $this->e(mb_strtoupper(mb_substr($person->name, 0, 1))) ?></span>
                                <bdi><?= $this->e($person->name) ?></bdi>
                                <?php if ($canEdit): ?>
                                    <form action="<?= $this->url($cardPath . '/members') ?>" method="post">
                                        <?= $this->csrfField() ?>
                                        <input type="hidden" name="user_id" value="<?= $memberId ?>">
                                        <button class="icon-btn" type="submit" title="<?= $this->te('Remove from card') ?>" aria-label="<?= $this->te('Remove from card') ?>"><?= $this->icon('close') ?></button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted small"><?= $this->te('Nobody is assigned yet.') ?></p>
                <?php endif; ?>
                <?php $unassigned = array_diff(array_keys($audience), $assigned); ?>
                <?php if ($canEdit && $unassigned): ?>
                    <form action="<?= $this->url($cardPath . '/members') ?>" method="post" class="inline-form">
                        <?= $this->csrfField() ?>
                        <select name="user_id" aria-label="<?= $this->te('Assign someone') ?>">
                            <?php foreach ($unassigned as $userId): ?>
                                <?php if (isset($people[$userId])): ?>
                                    <option value="<?= $userId ?>"><?= $this->e($people[$userId]->name) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-ghost" type="submit"><?= $this->icon('plus') ?><?= $this->te('Assign') ?></button>
                    </form>
                    <?= $this->partial('partials/field-error', ['field' => 'member']) ?>
                <?php endif; ?>
            </section>

            <section class="card stack">
                <h2 class="section-title"><?= $this->icon('tag') ?> <?= $this->te('Labels') ?></h2>
                <div class="label-picker">
                    <?php foreach ($boardLabels as $label): ?>
                        <?php if ($canEdit): ?>
                            <form action="<?= $this->url($cardPath . '/labels/' . $label->id) ?>" method="post">
                                <?= $this->csrfField() ?>
                                <button class="label label-<?= $this->e($label->color) ?> <?= isset($attached[$label->id]) ? 'is-on' : 'is-off' ?>" type="submit"
                                        aria-pressed="<?= isset($attached[$label->id]) ? 'true' : 'false' ?>" dir="auto"><?= $this->e($label->name) ?></button>
                            </form>
                        <?php elseif (isset($attached[$label->id])): ?>
                            <span class="label label-<?= $this->e($label->color) ?>" dir="auto"><?= $this->e($label->name) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!$boardLabels): ?><p class="muted small"><?= $this->te('No labels on this board yet.') ?></p><?php endif; ?>
                </div>
                <?php if ($canLabels && $canEdit): ?>
                    <form action="<?= $this->url('/boards/' . $board->publicId . '/labels') ?>" method="post" class="new-label">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="card" value="<?= $this->e($card->publicId) ?>">
                        <input name="name" required maxlength="60" dir="auto" placeholder="<?= $this->te('New label') ?>" aria-label="<?= $this->te('New label') ?>">
                        <select name="color" aria-label="<?= $this->te('Color') ?>">
                            <?php foreach (Label::COLORS as $color): ?>
                                <option value="<?= $color ?>"><?= $this->te(ucfirst($color)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-ghost" type="submit"><?= $this->icon('plus') ?></button>
                    </form>
                <?php endif; ?>
            </section>

            <?php if ($canEdit || $canMove || $canArchive): ?>
                <section class="card stack">
                    <h2 class="section-title"><?= $this->te('Actions') ?></h2>
                    <?php if ($canEdit): ?>
                        <form action="<?= $this->url($cardPath . '/complete') ?>" method="post">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="done" value="<?= $card->isComplete() ? '0' : '1' ?>">
                            <input type="hidden" name="back" value="<?= $this->e($cardPath) ?>">
                            <button class="btn btn-ghost btn-block" type="submit"><?= $this->icon('check') ?><?= $this->te($card->isComplete() ? 'Mark as not done' : 'Mark as done') ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canMove): ?>
                        <form action="<?= $this->url($cardPath . '/move') ?>" method="post" class="stack">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="back" value="<?= $this->e($cardPath) ?>">
                            <label><span><?= $this->te('Move to list') ?></span>
                                <select name="list_id">
                                    <?php foreach ($lists as $list): ?>
                                        <option value="<?= $list->id ?>" <?= $list->id === $card->listId ? 'selected' : '' ?>><?= $this->e($list->name) ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                            <button class="btn btn-ghost btn-block" type="submit"><?= $this->icon('right') ?><?= $this->te('Move') ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canArchive && !$card->archived): ?>
                        <form action="<?= $this->url($cardPath . '/archive') ?>" method="post">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-ghost btn-block danger" type="submit"><?= $this->icon('archive') ?><?= $this->te('Archive card') ?></button>
                        </form>
                    <?php elseif ($canArchive): ?>
                        <form action="<?= $this->url($cardPath . '/restore') ?>" method="post">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-ghost btn-block" type="submit"><?= $this->icon('restore') ?><?= $this->te('Restore card') ?></button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</main>
