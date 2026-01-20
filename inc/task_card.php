<?php

use Classes\Csrf;
use Classes\TaskRepository;

/** @var array $task */

$status   = $task['status'];
$order    = array_keys(TaskRepository::STATUSES);
$position = array_search($status, $order, true);
$prev     = $order[$position - 1] ?? null;
$next     = $order[$position + 1] ?? null;
$dueState = due_state($task['due_date'], $status);
$nextLabel = ['doing' => 'Start', 'done' => 'Done'];
$search   = mb_strtolower($task['title'] . ' ' . $task['notes'] . ' ' . $task['category']);
?>
<article class="task-card prio-<?= e($task['priority']) ?> <?= $status === 'done' ? 'is-done' : '' ?>"
         draggable="true"
         data-id="<?= (int) $task['id'] ?>"
         data-status="<?= e($status) ?>"
         data-priority="<?= e($task['priority']) ?>"
         data-due="<?= e($dueState ?? '') ?>"
         data-category="<?= e(mb_strtolower((string) $task['category'])) ?>"
         data-search="<?= e($search) ?>">

    <div class="task-meta">
        <span class="prio-badge" title="<?= e(TaskRepository::PRIORITIES[$task['priority']]) ?> priority">
            <?= icon('flag') ?><?= e(TaskRepository::PRIORITIES[$task['priority']]) ?>
        </span>
        <?php if ($task['category']): ?>
            <span class="pill <?= category_class($task['category']) ?>" dir="auto"><?= e($task['category']) ?></span>
        <?php endif; ?>
        <?php if ($task['due_date'] && $status !== 'done'): ?>
            <span class="due due-<?= e($dueState) ?>"><?= icon('calendar') ?><?= e(human_due($task['due_date'])) ?></span>
        <?php endif; ?>
    </div>

    <h3 class="task-title" dir="auto"><?= e($task['title']) ?></h3>

    <?php if ($task['notes']): ?>
        <p class="task-notes" dir="auto"><?= nl2br(e($task['notes'])) ?></p>
    <?php endif; ?>

    <footer class="task-actions">
        <?php if ($status === 'done'): ?>
            <span class="muted small"><?= icon('check') ?> <?= e(time_ago($task['completed_at'])) ?></span>
        <?php else: ?>
            <span class="muted small"><?= icon('clock') ?> <?= e(time_ago($task['created_at'])) ?></span>
        <?php endif; ?>

        <div class="action-row">
            <?php if ($prev): ?>
                <form action="<?= url('handle/goto.php') ?>" method="post" class="js-move">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                    <input type="hidden" name="status" value="<?= $prev ?>">
                    <button class="icon-btn" title="Move back to <?= e(TaskRepository::STATUSES[$prev]) ?>"><?= icon('left') ?></button>
                </form>
            <?php endif; ?>

            <a class="icon-btn" href="<?= url('edit.php?id=' . (int) $task['id']) ?>" title="Edit"><?= icon('edit') ?></a>

            <form action="<?= url('handle/delete.php') ?>" method="post" class="js-delete" data-confirm="Delete this task?">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                <button class="icon-btn danger" title="Delete"><?= icon('trash') ?></button>
            </form>

            <?php if ($next): ?>
                <form action="<?= url('handle/goto.php') ?>" method="post" class="js-move">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                    <input type="hidden" name="status" value="<?= $next ?>">
                    <button class="move-btn to-<?= $next ?>"><?= e($nextLabel[$next]) ?> <?= icon($next === 'done' ? 'check' : 'right') ?></button>
                </form>
            <?php endif; ?>
        </div>
    </footer>
</article>
