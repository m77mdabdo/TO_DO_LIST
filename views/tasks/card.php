<?php
/**
 * @var App\View\ViewHelpers $v
 * @var App\Task\Task $task
 */

use App\Task\Status;

$today    = $v->today();
$status   = $task->status;
$prev     = $status->previous();
$next     = $status->next();
$dueState = $task->isDone() ? null : $task->dueDate?->state($today);
$moveUrl  = $v->url("/tasks/{$task->id}/move");
?>
<article class="task-card prio-<?= $task->priority->value ?> <?= $task->isDone() ? 'is-done' : '' ?>"
         draggable="true"
         data-id="<?= $task->id ?>"
         data-status="<?= $status->value ?>"
         data-priority="<?= $task->priority->value ?>"
         data-due="<?= $dueState ?? '' ?>"
         data-category="<?= $v->e(mb_strtolower((string) $task->category)) ?>"
         data-search="<?= $v->e($task->searchText()) ?>"
         data-move-url="<?= $moveUrl ?>">

    <div class="task-meta">
        <span class="prio-badge" title="<?= $task->priority->label() ?> priority">
            <?= $v->icon('flag') ?><?= $task->priority->label() ?>
        </span>
        <?php if ($task->category): ?>
            <span class="pill <?= $v->categoryClass($task->category) ?>" dir="auto"><?= $v->e($task->category) ?></span>
        <?php endif; ?>
        <?php if ($dueState): ?>
            <span class="due due-<?= $dueState ?>"><?= $v->icon('calendar') ?><?= $v->e($task->dueDate->label($today)) ?></span>
        <?php endif; ?>
    </div>

    <h3 class="task-title" dir="auto"><?= $v->e($task->title) ?></h3>

    <?php if ($task->notes): ?>
        <p class="task-notes" dir="auto"><?= nl2br($v->e($task->notes)) ?></p>
    <?php endif; ?>

    <footer class="task-actions">
        <?php if ($task->isDone()): ?>
            <span class="muted small"><?= $v->icon('check') ?> <?= $v->e($v->timeAgo($task->completedAt)) ?></span>
        <?php else: ?>
            <span class="muted small"><?= $v->icon('clock') ?> <?= $v->e($v->timeAgo($task->createdAt)) ?></span>
        <?php endif; ?>

        <div class="action-row">
            <?php if ($prev): ?>
                <form action="<?= $moveUrl ?>" method="post" class="js-move">
                    <?= $v->csrfField() ?>
                    <input type="hidden" name="status" value="<?= $prev->value ?>">
                    <button class="icon-btn" title="Move back to <?= $prev->label() ?>"><?= $v->icon('left') ?></button>
                </form>
            <?php endif; ?>

            <a class="icon-btn" href="<?= $v->url("/tasks/{$task->id}/edit") ?>" title="Edit"><?= $v->icon('edit') ?></a>

            <form action="<?= $v->url("/tasks/{$task->id}/delete") ?>" method="post" class="js-delete" data-confirm="Delete this task?">
                <?= $v->csrfField() ?>
                <button class="icon-btn danger" title="Delete"><?= $v->icon('trash') ?></button>
            </form>

            <?php if ($next): ?>
                <form action="<?= $moveUrl ?>" method="post" class="js-move">
                    <?= $v->csrfField() ?>
                    <input type="hidden" name="status" value="<?= $next->value ?>">
                    <button class="move-btn to-<?= $next->value ?>">
                        <?= $next->actionLabel() ?> <?= $v->icon($next === Status::Done ? 'check' : 'right') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </footer>
</article>
