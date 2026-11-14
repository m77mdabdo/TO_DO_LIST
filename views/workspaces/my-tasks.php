<?php
/**
 * Everything given to me: cards I am on and checklist items assigned to me,
 * on every board I can see, grouped by when they are due.
 *
 * @var \App\View\View $this
 * @var array<string, list<array{kind: string, card: \App\Modules\Card\Contract\CardSummary, item?: \App\Modules\Checklist\Contract\ChecklistItem, due: ?string}>> $groups
 * @var array<int, \App\Modules\Board\Contract\BoardSummary> $boards
 * @var int $count
 */
$titles = ['overdue' => 'Overdue', 'today' => 'Due today', 'week' => 'Due in the next 7 days', 'later' => 'Later or no due date'];
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('workspaces/views-nav', ['current' => 'my-tasks']) ?>

<main class="wrap view-page stack my-tasks">
    <?php if ($count === 0): ?>
        <p class="card muted center"><?= $this->te('Nothing is assigned to you right now. Cards and checklist items given to you will show up here.') ?></p>
    <?php endif; ?>
    <?php foreach ($groups as $key => $rows): ?>
        <?php if ($rows === []) {
            continue;
        } ?>
        <section class="card stack-sm task-group task-<?= $key ?>">
            <h2 class="section-title"><?= $this->te($titles[$key]) ?> <span class="count"><?= count($rows) ?></span></h2>
            <ul class="task-list">
                <?php foreach ($rows as $row): ?>
                    <?php $board = $boards[$row['card']->boardId] ?? null; ?>
                    <li class="task-row">
                        <span class="task-kind" title="<?= $this->te($row['kind'] === 'card' ? 'Card' : 'Checklist item') ?>"><?= $this->icon($row['kind'] === 'card' ? 'board' : 'checklist') ?></span>
                        <div class="task-text">
                            <?php if ($row['kind'] === 'item'): ?>
                                <a href="<?= $this->url('/cards/' . $row['card']->publicId) ?>" dir="auto"><?= $this->e($row['item']->text) ?></a>
                                <span class="muted small" dir="auto"><?= $this->te('on :card', ['card' => $row['card']->title]) ?></span>
                            <?php else: ?>
                                <a href="<?= $this->url('/cards/' . $row['card']->publicId) ?>" dir="auto"><?= $this->e($row['card']->title) ?></a>
                            <?php endif; ?>
                            <?php if ($board !== null): ?><span class="muted small" dir="auto"><?= $this->e($board->name) ?></span><?php endif; ?>
                        </div>
                        <?php if ($row['due'] !== null): ?><span class="due <?= $key === 'overdue' ? 'due-late' : ($key === 'today' ? 'due-soon' : '') ?>"><?= $this->icon('clock') ?><?= $this->e($row['due']) ?></span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</main>
