<?php
/**
 * The board as a table: one row per card, sortable by any column.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Access\Contract\BoardRole $role
 * @var list<\App\Modules\Card\Contract\CardSummary> $rows
 * @var array<int, string> $listName list id => name
 * @var array<int, list<\App\Modules\Label\Contract\Label>> $cardLabels
 * @var array<int, list<int>> $cardMembers
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var array<int, array{done: int, total: int}> $progress added by the Checklist module
 * @var string $sort
 * @var bool $desc
 * @var array<string, string> $query
 * @var \DateTimeImmutable $today
 */
$progress ??= [];
$base = '/boards/' . $board->publicId . '/table';
$columns = ['title' => 'Card', 'list' => 'List', 'start' => 'Start', 'due' => 'Due', 'status' => 'Status'];
$link = function (string $column) use ($base, $query, $sort, $desc): string {
    $dir = $column === $sort && !$desc ? 'desc' : 'asc';

    return $this->url($base . '?' . http_build_query($query + ['sort' => $column, 'dir' => $dir]));
};
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('boards/view-bar', ['board' => $board, 'role' => $role, 'current' => '/table', 'query' => $query]) ?>

<div class="board-tools">
    <?= $this->partial('boards/filters', compact('board', 'filter', 'boardLabels', 'audience', 'people', 'totalCards', 'shownCards', 'viewPath')) ?>
</div>

<main class="wrap view-page">
    <?php if (!$rows): ?>
        <p class="card muted center"><?= $this->te($totalCards > 0 ? 'No card matches these filters.' : 'This board has no cards yet.') ?></p>
    <?php else: ?>
        <div class="table-wrap card">
            <table class="data-table">
                <caption class="visually-hidden"><?= $this->te('Cards on this board') ?></caption>
                <thead>
                    <tr>
                        <?php foreach ($columns as $column => $label): ?>
                            <th scope="col" <?= $column === $sort ? 'aria-sort="' . ($desc ? 'descending' : 'ascending') . '"' : '' ?>>
                                <a href="<?= $this->e($link($column)) ?>"><?= $this->te($label) ?><?php if ($column === $sort): ?> <span aria-hidden="true"><?= $desc ? '↓' : '↑' ?></span><?php endif; ?></a>
                            </th>
                        <?php endforeach; ?>
                        <th scope="col"><?= $this->te('Labels') ?></th>
                        <th scope="col"><?= $this->te('People') ?></th>
                        <th scope="col"><?= $this->te('Checklist') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $card): ?>
                        <?php $late = $card->isOverdue($today); ?>
                        <tr class="<?= $card->isComplete() ? 'is-done' : '' ?>">
                            <th scope="row"><a href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"><?= $this->e($card->title) ?></a></th>
                            <td dir="auto"><?= $this->e($listName[$card->listId] ?? '') ?></td>
                            <td class="nowrap"><?= $this->e((string) $card->startDate()) ?></td>
                            <td class="nowrap <?= $late ? 'danger' : '' ?>"><?= $this->e((string) $card->dueDate()) ?></td>
                            <td><span class="status status-<?= $card->isComplete() ? 'done' : ($late ? 'late' : 'open') ?>"><?= $this->te($card->isComplete() ? 'Done' : ($late ? 'Overdue' : 'Open')) ?></span></td>
                            <td>
                                <?php foreach ($cardLabels[$card->id] ?? [] as $label): ?>
                                    <span class="label label-<?= $this->e($label->color) ?>" dir="auto"><?= $this->e($label->name) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?php foreach ($cardMembers[$card->id] ?? [] as $userId): ?>
                                    <?php if (isset($people[$userId])): ?><span class="avatar avatar-xs" title="<?= $this->e($people[$userId]->name) ?>"><?= $this->e(mb_strtoupper(mb_substr($people[$userId]->name, 0, 1))) ?></span><?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                            <td class="nowrap"><?php if (isset($progress[$card->id])): ?><?= $progress[$card->id]['done'] ?>/<?= $progress[$card->id]['total'] ?><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted small"><?= $this->te(':count cards', ['count' => (string) count($rows)]) ?></p>
    <?php endif; ?>
</main>
