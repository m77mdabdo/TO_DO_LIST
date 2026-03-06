<?php
/**
 * @var App\View\ViewHelpers $v
 * @var App\View\View $view
 * @var App\Task\Task $task
 * @var list<string> $categories
 */

use App\Task\Priority;
use App\Task\TaskRules;

// what the user typed last time wins over what is saved
$value = fn (string $field, ?string $saved) => $v->hasOld() ? $v->old($field) : (string) $saved;
?>
<main class="form-shell">
    <a class="back-link" href="<?= $v->url('/') ?>"><?= $v->icon('left') ?> Back to board</a>

    <div class="card form-card">
        <div class="form-head">
            <span class="status-pill status-<?= $task->status->value ?>"><?= $task->status->label() ?></span>
            <h1>Edit task</h1>
            <p class="muted small">Created <?= $v->e($task->createdAt->format('M j, Y · g:i A')) ?></p>
        </div>

        <form action="<?= $v->url("/tasks/{$task->id}") ?>" method="post" class="stack" novalidate>
            <?= $v->csrfField() ?>

            <label>
                <span>Title</span>
                <input type="text" name="title" maxlength="<?= TaskRules::TITLE_MAX ?>" required dir="auto"
                       value="<?= $v->e($value('title', $task->title)) ?>" aria-invalid="<?= $v->invalid('title') ?>">
                <?= $view->partial('partials/field_error', ['field' => 'title']) ?>
            </label>

            <label>
                <span>Notes</span>
                <textarea name="notes" rows="5" maxlength="<?= TaskRules::NOTES_MAX ?>" dir="auto"><?= $v->e($value('notes', $task->notes)) ?></textarea>
                <?= $view->partial('partials/field_error', ['field' => 'notes']) ?>
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
                    <input type="date" name="due_date" value="<?= $v->e($value('due_date', $task->dueDate?->toString())) ?>">
                    <?= $view->partial('partials/field_error', ['field' => 'due_date']) ?>
                </label>
                <label>
                    <span>List</span>
                    <input type="text" name="category" maxlength="<?= TaskRules::CATEGORY_MAX ?>" dir="auto" list="category-list"
                           value="<?= $v->e($value('category', $task->category)) ?>">
                    <?= $view->partial('partials/field_error', ['field' => 'category']) ?>
                </label>
            </div>
            <?= $view->partial('tasks/category_list', ['categories' => $categories]) ?>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $v->icon('check') ?> Save changes</button>
                <a class="btn btn-ghost" href="<?= $v->url('/') ?>">Cancel</a>
            </div>
        </form>

        <form action="<?= $v->url("/tasks/{$task->id}/delete") ?>" method="post" class="js-delete danger-zone" data-confirm="Delete this task for good?">
            <?= $v->csrfField() ?>
            <button class="btn btn-danger-ghost" type="submit"><?= $v->icon('trash') ?> Delete task</button>
        </form>
    </div>
</main>
