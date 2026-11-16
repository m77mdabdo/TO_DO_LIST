<?php
/**
 * Checklists on the card page (slot "card.main", filled by the Checklist module).
 *
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var list<\App\Modules\Checklist\Contract\Checklist> $checklists
 * @var array<int, \App\Modules\Identity\Contract\User> $audience people who can see the board
 * @var bool $canEditChecklists
 * @var \DateTimeImmutable $today
 * @var list<int> $hiddenDone checklists whose ticked items this person hides
 * @var array<int, \App\Modules\Card\Contract\CardSummary|null> $linkedCards cards made from items (null when this person can't open them)
 * @var list<array{id: int, title: string, card: string, items: int}> $copySources checklists on other cards of the board
 * @var list<array{board: \App\Modules\Board\Contract\BoardSummary, lists: list<\App\Modules\Board\Contract\ListSummary>}> $convertTargets
 */
$form = $this->form;
$canEdit = $canEditChecklists;
$hiddenDone ??= [];
$linkedCards ??= [];
$copySources ??= [];
$convertTargets ??= [];
?>
<section class="checklists stack" id="checklists" aria-label="<?= $this->te('Checklists') ?>">
    <?php foreach ($checklists as $checklist): ?>
        <?php $percent = $checklist->percent(); $hiding = in_array($checklist->id, $hiddenDone, true); $doneCount = $checklist->doneCount(); ?>
        <div class="checklist" id="checklist-<?= $checklist->id ?>">
            <div class="checklist-head">
                <h2 class="section-title"><?= $this->icon('checklist') ?> <span dir="auto"><?= $this->e($checklist->title) ?></span></h2>
                <span class="muted small"><?= $doneCount ?>/<?= count($checklist->items) ?></span>
                <?php if ($doneCount > 0 || $hiding): ?>
                    <form action="<?= $this->url('/checklists/' . $checklist->id . '/hide-done') ?>" method="post" class="checklist-hide">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="hide" value="<?= $hiding ? '0' : '1' ?>">
                        <button class="btn btn-ghost btn-sm" type="submit" aria-pressed="<?= $hiding ? 'true' : 'false' ?>">
                            <?= $hiding ? $this->te('Show checked items (:count)', ['count' => (string) $doneCount]) : $this->te('Hide checked items') ?></button>
                    </form>
                <?php endif; ?>
                <?php if ($canEdit): ?>
                    <details class="menu menu-end">
                        <summary class="icon-btn" title="<?= $this->te('Checklist actions') ?>" aria-label="<?= $this->te('Checklist actions') ?>"><?= $this->icon('more') ?></summary>
                        <div class="menu-panel stack">
                            <form action="<?= $this->url('/checklists/' . $checklist->id) ?>" method="post" class="stack">
                                <?= $this->csrfField() ?>
                                <label><span><?= $this->te('Title') ?></span>
                                    <input name="title" required maxlength="120" dir="auto" value="<?= $this->e($checklist->title) ?>"></label>
                                <button class="btn btn-ghost" type="submit"><?= $this->te('Rename') ?></button>
                            </form>
                            <form action="<?= $this->url('/checklists/' . $checklist->id . '/delete') ?>" method="post"
                                  data-confirm="<?= $this->te('Delete this checklist and its items?') ?>">
                                <?= $this->csrfField() ?>
                                <button class="btn btn-ghost btn-block danger" type="submit"><?= $this->icon('trash') ?><?= $this->te('Delete checklist') ?></button>
                            </form>
                        </div>
                    </details>
                <?php endif; ?>
            </div>

            <progress class="checklist-progress <?= $percent === 100 ? 'is-complete' : '' ?>" max="100" value="<?= $percent ?>"
                      aria-label="<?= $this->te(':n% done', ['n' => $percent]) ?>"><?= $percent ?>%</progress>

            <ul class="checklist-items js-items" data-checklist-id="<?= $checklist->id ?>">
                <?php foreach ($checklist->items as $item): if ($hiding && $item->isDone()) continue; ?>
                    <?php $assignee = $item->assigneeId !== null ? ($audience[$item->assigneeId] ?? null) : null; ?>
                    <li class="checklist-item <?= $item->isDone() ? 'is-done' : '' ?>" data-item-id="<?= $item->id ?>"
                        <?php if ($canEdit): ?>draggable="true" data-move-url="<?= $this->url('/checklist-items/' . $item->id . '/move') ?>"<?php endif; ?>>
                        <?php if ($canEdit): ?>
                            <form action="<?= $this->url('/checklist-items/' . $item->id . '/toggle') ?>" method="post">
                                <?= $this->csrfField() ?>
                                <button class="check-btn <?= $item->isDone() ? 'is-checked' : '' ?>" type="submit" aria-pressed="<?= $item->isDone() ? 'true' : 'false' ?>"
                                        title="<?= $this->te($item->isDone() ? 'Mark as not done' : 'Mark as done') ?>"><?= $this->icon('check') ?></button>
                            </form>
                        <?php else: ?>
                            <span class="check-btn <?= $item->isDone() ? 'is-checked' : '' ?>" aria-hidden="true"><?= $this->icon('check') ?></span>
                        <?php endif; ?>

                        <span class="item-text" dir="auto"><?= $this->e($item->text) ?>
                            <?php if ($item->cardLinkId !== null): $linked = $linkedCards[$item->cardLinkId] ?? null; ?>
                                <?php if ($linked !== null): ?>
                                    <a class="item-card-link" href="<?= $this->url('/cards/' . $linked->publicId) ?>"><?= $this->icon('board') ?><span dir="auto"><?= $this->e($linked->title) ?></span></a>
                                <?php else: ?>
                                    <span class="item-card-link muted"><?= $this->icon('lock') ?><?= $this->te('Turned into a card you cannot open') ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>

                        <span class="item-meta">
                            <?php if ($item->dueDate()): ?>
                                <span class="due <?= $item->isOverdue($today) ? 'due-overdue' : '' ?>"><?= $this->icon('calendar') ?><?= $this->e($item->dueDate()) ?></span>
                            <?php endif; ?>
                            <?php if ($assignee !== null): ?>
                                <span class="avatar avatar-xs" title="<?= $this->e($assignee->name) ?>"><?= $this->e(mb_strtoupper(mb_substr($assignee->name, 0, 1))) ?></span>
                            <?php endif; ?>
                        </span>

                        <?php if ($canEdit): ?>
                            <details class="menu menu-end item-menu">
                                <summary class="icon-btn" title="<?= $this->te('Edit item') ?>" aria-label="<?= $this->te('Edit item') ?>"><?= $this->icon('edit') ?></summary>
                                <div class="menu-panel stack">
                                    <form action="<?= $this->url('/checklist-items/' . $item->id) ?>" method="post" class="stack">
                                        <?= $this->csrfField() ?>
                                        <label><span><?= $this->te('Item') ?></span>
                                            <input name="text" required maxlength="500" dir="auto" value="<?= $this->e($item->text) ?>"></label>
                                        <label><span><?= $this->te('Assigned to') ?></span>
                                            <select name="assignee_id">
                                                <option value=""><?= $this->te('Nobody') ?></option>
                                                <?php foreach ($audience as $person): ?>
                                                    <option value="<?= $person->id ?>" <?= $person->id === $item->assigneeId ? 'selected' : '' ?>><?= $this->e($person->name) ?></option>
                                                <?php endforeach; ?>
                                            </select></label>
                                        <label><span><?= $this->te('Due date') ?></span>
                                            <input type="date" name="due_date" value="<?= $this->e((string) $item->dueDate()) ?>"></label>
                                        <button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button>
                                    </form>
                                    <?php if ($convertTargets && $item->cardLinkId === null): ?>
                                        <form action="<?= $this->url('/checklist-items/' . $item->id . '/convert') ?>" method="post" class="stack-sm">
                                            <?= $this->csrfField() ?>
                                            <label><span><?= $this->te('Turn into a card in') ?></span>
                                                <select name="target">
                                                    <?php foreach ($convertTargets as ['board' => $targetBoard, 'lists' => $targetLists]): ?>
                                                        <optgroup label="<?= $this->e($targetBoard->name) ?>">
                                                            <?php foreach ($targetLists as $targetList): ?>
                                                                <option value="<?= $this->e($targetBoard->publicId . ':' . $targetList->id) ?>" <?= $targetList->id === $card->listId ? 'selected' : '' ?>><?= $this->e($targetList->name) ?></option>
                                                            <?php endforeach; ?>
                                                        </optgroup>
                                                    <?php endforeach; ?>
                                                </select></label>
                                            <small class="muted"><?= $this->te('The card gets the item’s text, due date and person, and a link back here.') ?></small>
                                            <button class="btn btn-ghost" type="submit"><?= $this->icon('plus') ?><?= $this->te('Turn into a card') ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <form action="<?= $this->url('/checklist-items/' . $item->id . '/delete') ?>" method="post">
                                        <?= $this->csrfField() ?>
                                        <button class="btn btn-ghost btn-block danger" type="submit"><?= $this->icon('trash') ?><?= $this->te('Delete item') ?></button>
                                    </form>
                                </div>
                            </details>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($hiding && $doneCount > 0): ?>
                <p class="muted small"><?= $this->te(':count checked items are hidden.', ['count' => (string) $doneCount]) ?></p>
            <?php endif; ?>

            <?php if ($canEdit): ?>
                <form action="<?= $this->url('/checklists/' . $checklist->id . '/items') ?>" method="post" class="inline-form add-item">
                    <?= $this->csrfField() ?>
                    <textarea name="text" required rows="1" dir="auto" class="js-add-item" placeholder="<?= $this->te('Add an item') ?>" aria-label="<?= $this->te('Add an item') ?>"
                              aria-describedby="add-item-help-<?= $checklist->id ?>"></textarea>
                    <button class="btn btn-ghost" type="submit" title="<?= $this->te('Add') ?>"><?= $this->icon('plus') ?><span class="visually-hidden"><?= $this->te('Add') ?></span></button>
                </form>
                <small class="muted" id="add-item-help-<?= $checklist->id ?>"><?= $this->te('Paste several lines to add one item per line. Shift+Enter starts a new line.') ?></small>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($canEdit): ?>
        <details class="add-checklist" <?= $form->error('checklist') ? 'open' : '' ?>>
            <summary class="btn btn-ghost"><?= $this->icon('checklist') ?><?= $this->te('Add a checklist') ?></summary>
            <form action="<?= $this->url('/cards/' . $card->publicId . '/checklists') ?>" method="post" class="inline-form">
                <?= $this->csrfField() ?>
                <input name="title" required maxlength="120" dir="auto" value="<?= $this->te('Checklist') ?>" aria-label="<?= $this->te('Checklist title') ?>">
                <button class="btn btn-primary" type="submit"><?= $this->te('Add') ?></button>
            </form>
        </details>
        <?php if ($copySources): ?>
            <details class="copy-checklist" <?= $form->error('copy_from') ? 'open' : '' ?>>
                <summary class="btn btn-ghost"><?= $this->icon('copy') ?><?= $this->te('Copy a checklist from another card') ?></summary>
                <form action="<?= $this->url('/cards/' . $card->publicId . '/checklists/copy') ?>" method="post" class="inline-form">
                    <?= $this->csrfField() ?>
                    <select name="from" aria-label="<?= $this->te('Checklist to copy') ?>">
                        <?php foreach ($copySources as $source): ?>
                            <option value="<?= $source['id'] ?>"><?= $this->e($source['card'] . ' › ' . $source['title']) ?> (<?= $source['items'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" type="submit"><?= $this->te('Copy') ?></button>
                </form>
                <small class="muted"><?= $this->te('The items come unticked, with their people and due dates.') ?></small>
            </details>
            <?= $this->partial('partials/field-error', ['field' => 'copy_from']) ?>
        <?php endif; ?>
        <?= $this->partial('partials/field-error', ['field' => 'convert_to']) ?>
        <?= $this->partial('partials/field-error', ['field' => 'checklist']) ?>
        <?= $this->partial('partials/field-error', ['field' => 'text']) ?>
    <?php endif; ?>
</section>
