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
 */
$form = $this->form;
$canEdit = $canEditChecklists;
?>
<section class="checklists stack" id="checklists" aria-label="<?= $this->te('Checklists') ?>">
    <?php foreach ($checklists as $checklist): ?>
        <?php $percent = $checklist->percent(); ?>
        <div class="checklist">
            <div class="checklist-head">
                <h2 class="section-title"><?= $this->icon('checklist') ?> <span dir="auto"><?= $this->e($checklist->title) ?></span></h2>
                <span class="muted small"><?= $checklist->doneCount() ?>/<?= count($checklist->items) ?></span>
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
                <?php foreach ($checklist->items as $item): ?>
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

                        <span class="item-text" dir="auto"><?= $this->e($item->text) ?></span>

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

            <?php if ($canEdit): ?>
                <form action="<?= $this->url('/checklists/' . $checklist->id . '/items') ?>" method="post" class="inline-form add-item">
                    <?= $this->csrfField() ?>
                    <input name="text" required maxlength="500" dir="auto" placeholder="<?= $this->te('Add an item') ?>" aria-label="<?= $this->te('Add an item') ?>">
                    <button class="btn btn-ghost" type="submit"><?= $this->icon('plus') ?></button>
                </form>
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
        <?= $this->partial('partials/field-error', ['field' => 'checklist']) ?>
        <?= $this->partial('partials/field-error', ['field' => 'text']) ?>
    <?php endif; ?>
</section>
