<?php
/**
 * @var \App\View\View $this
 * @var \App\Task\Task $task
 * @var \DateTimeImmutable $today
 * @var \App\Support\TimeAgo $timeAgo
 */
$status   = $task->status;
$previous = $status->previous();
$next     = $status->next();
$dueState = $task->isDone() ? null : $task->dueDate?->state($today);
$moveUrl  = $this->url("/tasks/{$task->id}/move");
?>
<article class="task-card prio-<?= $task->priority->value ?> <?= $task->isDone() ? 'is-done' : '' ?>"
         draggable="true"
         data-id="<?= $task->id ?>"
         data-move-url="<?= $this->e($moveUrl) ?>"
         data-status="<?= $status->value ?>"
         data-priority="<?= $task->priority->value ?>"
         data-due="<?= $this->e($dueState ?? '') ?>"
         data-category="<?= $this->e(mb_strtolower((string) $task->category)) ?>"
         data-search="<?= $this->e($task->searchText()) ?>">

    <div class="task-meta">
        <span class="prio-badge" title="<?= $task->priority->label() ?> priority">
            <?= $this->icon('flag') ?><?= $task->priority->label() ?>
        </span>
        <?php if ($task->category): ?>
            <span class="pill <?= $this->categoryClass($task->category) ?>" dir="auto"><?= $this->e($task->category) ?></span>
        <?php endif; ?>
        <?php if ($dueState): ?>
            <span class="due due-<?= $dueState ?>"><?= $this->icon('calendar') ?><?= $this->e($task->dueDate->label($today)) ?></span>
        <?php endif; ?>
    </div>

    <h3 class="task-title" dir="auto"><?= $this->e($task->title) ?></h3>

    <?php if ($task->notes): ?>
        <p class="task-notes" dir="auto"><?= nl2br($this->e($task->notes)) ?></p>
    <?php endif; ?>

    <footer class="task-actions">
        <?php if ($task->isDone()): ?>
            <span class="muted small"><?= $this->icon('check') ?> <?= $this->e($timeAgo->format($task->completedAt)) ?></span>
        <?php else: ?>
            <span class="muted small"><?= $this->icon('clock') ?> <?= $this->e($timeAgo->format($task->createdAt)) ?></span>
        <?php endif; ?>

        <div class="action-row">
            <?php if ($previous): ?>
                <form action="<?= $this->e($moveUrl) ?>" method="post" class="js-move">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="status" value="<?= $previous->value ?>">
                    <button class="icon-btn" title="Move back to <?= $previous->label() ?>"><?= $this->icon('left') ?></button>
                </form>
            <?php endif; ?>

            <a class="icon-btn" href="<?= $this->url("/tasks/{$task->id}/edit") ?>" title="Edit"><?= $this->icon('edit') ?></a>

            <form action="<?= $this->url("/tasks/{$task->id}/delete") ?>" method="post" class="js-delete" data-confirm="Delete this task?">
                <?= $this->csrfField() ?>
                <button class="icon-btn danger" title="Delete"><?= $this->icon('trash') ?></button>
            </form>

            <?php if ($next): ?>
                <form action="<?= $this->e($moveUrl) ?>" method="post" class="js-move">
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="status" value="<?= $next->value ?>">
                    <button class="move-btn to-<?= $next->value ?>"><?= $next->actionLabel() ?> <?= $this->icon($next === \App\Task\Status::Done ? 'check' : 'right') ?></button>
                </form>
            <?php endif; ?>
        </div>
    </footer>
</article>
