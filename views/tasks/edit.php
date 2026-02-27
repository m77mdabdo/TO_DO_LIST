<?php
/**
 * @var \App\View\View $this
 * @var \App\Task\Task $task
 * @var list<string> $categories
 */
use App\Task\Priority;
use App\Task\TaskRules;

$form  = $this->form;
$value = fn (string $field, ?string $current) => $form->old($field, (string) $current);
?>
<main class="form-shell">
    <a class="back-link" href="<?= $this->url('/') ?>"><?= $this->icon('left') ?> Back to board</a>

    <div class="card form-card">
        <div class="form-head">
            <span class="status-pill status-<?= $task->status->value ?>"><?= $task->status->label() ?></span>
            <h1>Edit task</h1>
            <p class="muted small">Created <?= $this->e($task->createdAt->format('M j, Y · g:i A')) ?></p>
        </div>

        <form action="<?= $this->url("/tasks/{$task->id}") ?>" method="post" class="stack" novalidate>
            <?= $this->csrfField() ?>

            <label>
                <span>Title</span>
                <input type="text" name="title" maxlength="<?= TaskRules::TITLE_MAX ?>" required dir="auto" value="<?= $this->e($value('title', $task->title)) ?>"
                       aria-invalid="<?= $form->error('title') ? 'true' : 'false' ?>">
                <?= $this->partial('partials/field-error', ['field' => 'title']) ?>
            </label>

            <label>
                <span>Notes</span>
                <textarea name="notes" rows="5" maxlength="<?= TaskRules::NOTES_MAX ?>" dir="auto"><?= $this->e($value('notes', $task->notes)) ?></textarea>
                <?= $this->partial('partials/field-error', ['field' => 'notes']) ?>
            </label>

            <div class="grid-3">
                <label>
                    <span>Priority</span>
                    <select name="priority">
                        <?php foreach (Priority::cases() as $priority): ?>
                            <option value="<?= $priority->value ?>" <?= $value('priority', $task->priority->value) === $priority->value ? 'selected' : '' ?>><?= $priority->label() ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Due date</span>
                    <input type="date" name="due_date" value="<?= $this->e($value('due_date', $task->dueDate?->toString())) ?>">
                    <?= $this->partial('partials/field-error', ['field' => 'due_date']) ?>
                </label>
                <label>
                    <span>List</span>
                    <input type="text" name="category" maxlength="<?= TaskRules::CATEGORY_MAX ?>" dir="auto" list="category-list" value="<?= $this->e($value('category', $task->category)) ?>">
                    <?= $this->partial('partials/field-error', ['field' => 'category']) ?>
                </label>
            </div>
            <?= $this->partial('partials/category-list', ['categories' => $categories]) ?>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $this->icon('check') ?> Save changes</button>
                <a class="btn btn-ghost" href="<?= $this->url('/') ?>">Cancel</a>
            </div>
        </form>

        <form action="<?= $this->url("/tasks/{$task->id}/delete") ?>" method="post" class="js-delete danger-zone" data-confirm="Delete this task for good?">
            <?= $this->csrfField() ?>
            <button class="btn btn-danger-ghost" type="submit"><?= $this->icon('trash') ?> Delete task</button>
        </form>
    </div>
</main>
