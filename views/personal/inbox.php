<?php
/**
 * The personal inbox: things captured quickly, kept only for this person
 * until they move them to a board.
 *
 * @var \App\View\View $this
 * @var list<array{id: int, public_id: string, title: string, description: ?string, due_date: ?string, created_at: string, files: list<array{public_id: string, name: string, mime: string, size: int}>}> $items
 * @var list<array{workspace: \App\Modules\Workspace\Contract\WorkspaceSummary, board: \App\Modules\Board\Contract\BoardSummary, lists: list<\App\Modules\Board\Contract\ListSummary>}> $targets
 * @var int $max
 * @var mixed $editing public id of the item whose forms should be open
 */
$form = $this->form;
$size = static fn (int $bytes): string => $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('workspaces/views-nav', ['current' => 'inbox']) ?>

<main class="wrap view-page stack inbox-page">
    <section class="card stack-sm" id="capture" aria-labelledby="capture-title">
        <h2 class="section-title" id="capture-title"><?= $this->icon('plus') ?> <?= $this->te('Add to your inbox') ?></h2>
        <form action="<?= $this->url('/inbox') ?>" method="post" class="stack-sm">
            <?= $this->csrfField() ?>
            <label><span><?= $this->te('Title') ?></span>
                <input type="text" name="title" maxlength="200" required dir="auto" value="<?= $this->e($editing === null ? $form->old('title') : '') ?>"
                       aria-describedby="title-error" placeholder="<?= $this->te('Something to do or remember') ?>"></label>
            <?php if ($editing === null): ?><?= $this->partial('partials/field-error', ['field' => 'title']) ?><?php endif; ?>
            <details class="capture-more" <?= $editing === null && ($form->old('description') !== '' || $form->old('due_date') !== '') ? 'open' : '' ?>>
                <summary class="btn btn-ghost"><?= $this->te('Add a description or a date') ?></summary>
                <div class="stack-sm">
                    <label><span><?= $this->te('Description') ?></span>
                        <textarea name="description" rows="3" maxlength="10000" dir="auto"><?= $this->e($editing === null ? $form->old('description') : '') ?></textarea></label>
                    <label><span><?= $this->te('Due date') ?></span>
                        <input type="date" name="due_date" value="<?= $this->e($editing === null ? $form->old('due_date') : '') ?>"></label>
                    <?php if ($editing === null): ?><?= $this->partial('partials/field-error', ['field' => 'due_date']) ?><?php endif; ?>
                </div>
            </details>
            <small class="muted"><?= $this->te('Only you see your inbox. Press Q on any page to add something quickly. You can keep up to :count items.', ['count' => (string) $max]) ?></small>
            <div><button class="btn btn-primary" type="submit"><?= $this->te('Add') ?></button></div>
        </form>
    </section>

    <?php if ($items === []): ?>
        <p class="card muted center"><?= $this->te('Your inbox is empty. Things you add here wait until you move them to a board.') ?></p>
    <?php else: ?>
        <h2 class="section-title"><?= $this->te('In your inbox') ?> <span class="count"><?= count($items) ?></span></h2>
        <ul class="inbox-list stack-sm">
            <?php foreach ($items as $item): ?>
                <?php $open = $editing === $item['public_id']; $base = '/inbox/' . $item['public_id']; ?>
                <li class="card inbox-item stack-sm" id="item-<?= $this->e($item['public_id']) ?>">
                    <div class="inbox-head">
                        <h3 class="inbox-title" dir="auto"><?= $this->e($item['title']) ?></h3>
                        <?php if ($item['due_date'] !== null): ?><span class="due"><?= $this->icon('clock') ?><bdi><?= $this->e($item['due_date']) ?></bdi></span><?php endif; ?>
                    </div>
                    <?php if ($item['description'] !== null): ?><p class="inbox-description" dir="auto"><?= nl2br($this->e($item['description'])) ?></p><?php endif; ?>
                    <?php if ($item['files'] !== []): ?>
                        <ul class="inbox-files">
                            <?php foreach ($item['files'] as $file): ?>
                                <li>
                                    <?= $this->icon('paperclip') ?>
                                    <a href="<?= $this->url('/inbox-files/' . $file['public_id']) ?>" dir="auto"><?= $this->e($file['name']) ?></a>
                                    <small class="muted"><?= $size($file['size']) ?></small>
                                    <form action="<?= $this->url('/inbox-files/' . $file['public_id'] . '/delete') ?>" method="post" data-confirm="<?= $this->te('Remove this file?') ?>">
                                        <?= $this->csrfField() ?>
                                        <button class="icon-btn" type="submit" title="<?= $this->te('Remove file') ?>" aria-label="<?= $this->te('Remove file') ?>: <?= $this->e($file['name']) ?>"><?= $this->icon('close') ?></button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <small class="muted"><?= $this->te('Added :date', ['date' => substr($item['created_at'], 0, 16)]) ?></small>

                    <div class="inbox-actions">
                        <?php if ($targets !== []): ?>
                            <form action="<?= $this->url($base . '/move') ?>" method="post" class="inbox-move">
                                <?= $this->csrfField() ?>
                                <label><span class="visually-hidden"><?= $this->te('Move to') ?></span>
                                    <select name="to" required aria-label="<?= $this->te('Move to') ?>: <?= $this->e($item['title']) ?>">
                                        <option value=""><?= $this->te('Move to a board…') ?></option>
                                        <?php foreach ($targets as $target): ?>
                                            <optgroup label="<?= $this->e($target['workspace']->name . ' › ' . $target['board']->name) ?>">
                                                <?php foreach ($target['lists'] as $list): ?>
                                                    <option value="<?= $this->e($target['board']->publicId . ':' . $list->id) ?>"><?= $this->e($list->name) ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select></label>
                                <button class="btn btn-primary" type="submit"><?= $this->icon('right') ?><?= $this->te('Move') ?></button>
                            </form>
                        <?php endif; ?>
                        <details class="inbox-edit" <?= $open ? 'open' : '' ?>>
                            <summary class="btn btn-ghost"><?= $this->icon('edit') ?><?= $this->te('Edit') ?></summary>
                            <div class="stack-sm">
                                <form action="<?= $this->url($base) ?>" method="post" class="stack-sm">
                                    <?= $this->csrfField() ?>
                                    <label><span><?= $this->te('Title') ?></span>
                                        <input type="text" name="title" maxlength="200" required dir="auto" value="<?= $this->e($open ? $form->old('title', $item['title']) : $item['title']) ?>"></label>
                                    <label><span><?= $this->te('Description') ?></span>
                                        <textarea name="description" rows="3" maxlength="10000" dir="auto"><?= $this->e($open ? $form->old('description', (string) $item['description']) : (string) $item['description']) ?></textarea></label>
                                    <label><span><?= $this->te('Due date') ?></span>
                                        <input type="date" name="due_date" value="<?= $this->e($open ? $form->old('due_date', (string) $item['due_date']) : (string) $item['due_date']) ?>"></label>
                                    <?php if ($open): ?>
                                        <?= $this->partial('partials/field-error', ['field' => 'title']) ?>
                                        <?= $this->partial('partials/field-error', ['field' => 'description']) ?>
                                        <?= $this->partial('partials/field-error', ['field' => 'due_date']) ?>
                                    <?php endif; ?>
                                    <div><button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button></div>
                                </form>
                                <form action="<?= $this->url($base . '/files') ?>" method="post" enctype="multipart/form-data" class="stack-sm">
                                    <?= $this->csrfField() ?>
                                    <label><span><?= $this->te('Add a file') ?></span>
                                        <input type="file" name="file" required></label>
                                    <small class="muted"><?= $this->te('Up to 10 MB each, 10 files per item. The files go with the item to its card.') ?></small>
                                    <?php if ($open): ?><?= $this->partial('partials/field-error', ['field' => 'file']) ?><?php endif; ?>
                                    <div><button class="btn btn-ghost" type="submit"><?= $this->icon('upload') ?><?= $this->te('Upload') ?></button></div>
                                </form>
                            </div>
                        </details>
                        <form action="<?= $this->url($base . '/delete') ?>" method="post" data-confirm="<?= $this->te('Delete this inbox item and its files?') ?>">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-ghost danger" type="submit"><?= $this->icon('trash') ?><?= $this->te('Delete') ?></button>
                        </form>
                    </div>
                    <?php if ($open): ?><?= $this->partial('partials/field-error', ['field' => 'move_to']) ?><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($targets === []): ?>
            <p class="muted small"><?= $this->te('You cannot add cards to any board yet, so items stay here for now.') ?></p>
        <?php endif; ?>
        <p class="muted small"><?= $this->te('Moving an item makes a card with its title, description, due date and files, then removes it from your inbox.') ?></p>
    <?php endif; ?>
</main>
