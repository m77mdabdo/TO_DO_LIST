<?php

require_once __DIR__ . '/App.php';

use Classes\Csrf;
use Classes\TaskRepository;

$user = require_login();
$id   = $request->int('id');
$task = $id ? $tasks->find($user['id'], $id) : null;

if (!$task) {
    abort(404, "We couldn't find that task.");
}

$inlineErrors = true;
$fieldErrors  = errors();
$value = fn (string $key) => old($key, (string) ($task[$key] ?? ''));

$pageTitle = 'Edit task';
$bodyClass = 'page-form';
require __DIR__ . '/inc/header.php';
?>

<main class="form-shell">
    <a class="back-link" href="<?= url('index.php') ?>"><?= icon('left') ?> Back to board</a>

    <div class="card form-card">
        <div class="form-head">
            <span class="status-pill status-<?= e($task['status']) ?>"><?= e(TaskRepository::STATUSES[$task['status']]) ?></span>
            <h1>Edit task</h1>
            <p class="muted small">Created <?= e(date('M j, Y · g:i A', strtotime($task['created_at']))) ?></p>
        </div>

        <form action="<?= url('handle/edit.php') ?>" method="post" class="stack" novalidate>
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">

            <label>
                <span>Title</span>
                <input type="text" name="title" maxlength="150" required dir="auto" value="<?= e($value('title')) ?>"
                       aria-invalid="<?= isset($fieldErrors['title']) ? 'true' : 'false' ?>">
                <?php if (isset($fieldErrors['title'])): ?><small class="field-error"><?= e($fieldErrors['title']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Notes</span>
                <textarea name="notes" rows="5" maxlength="1000" dir="auto"><?= e($value('notes')) ?></textarea>
                <?php if (isset($fieldErrors['notes'])): ?><small class="field-error"><?= e($fieldErrors['notes']) ?></small><?php endif; ?>
            </label>

            <div class="grid-3">
                <label>
                    <span>Priority</span>
                    <select name="priority">
                        <?php foreach (TaskRepository::PRIORITIES as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $value('priority') === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Due date</span>
                    <input type="date" name="due_date" value="<?= e($value('due_date')) ?>">
                    <?php if (isset($fieldErrors['due_date'])): ?><small class="field-error"><?= e($fieldErrors['due_date']) ?></small><?php endif; ?>
                </label>
                <label>
                    <span>List</span>
                    <input type="text" name="category" maxlength="30" dir="auto" list="category-list" value="<?= e($value('category')) ?>">
                    <?php if (isset($fieldErrors['category'])): ?><small class="field-error"><?= e($fieldErrors['category']) ?></small><?php endif; ?>
                </label>
            </div>
            <datalist id="category-list">
                <?php foreach ($tasks->categories($user['id']) as $category): ?>
                    <option value="<?= e($category) ?>">
                <?php endforeach; ?>
            </datalist>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= icon('check') ?> Save changes</button>
                <a class="btn btn-ghost" href="<?= url('index.php') ?>">Cancel</a>
            </div>
        </form>

        <form action="<?= url('handle/delete.php') ?>" method="post" class="js-delete danger-zone" data-confirm="Delete this task for good?">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
            <button class="btn btn-danger-ghost" type="submit"><?= icon('trash') ?> Delete task</button>
        </form>
    </div>
</main>

<?php require __DIR__ . '/inc/footer.php'; ?>
