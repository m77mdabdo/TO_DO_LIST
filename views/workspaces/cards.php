<?php
/**
 * Every open card of the workspace's boards I can see, in one table.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var array<int, \App\Modules\Board\Contract\BoardSummary> $boards
 * @var string $chosen a board's public id, or ""
 * @var list<\App\Modules\Card\Contract\CardSummary> $rows
 * @var int $total
 * @var array<int, string> $lists
 * @var array<int, list<int>> $members
 * @var array<int, list<\App\Modules\Label\Contract\Label>> $labels
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var \App\Modules\Board\Contract\CardFilter $filter
 * @var \DateTimeImmutable $today
 */
$dueChoices = ['' => 'Any due date', 'overdue' => 'Overdue', 'today' => 'Due today', 'week' => 'Due in the next 7 days', 'none' => 'No due date'];
$statusChoices = ['' => 'Open and done', 'open' => 'Open', 'done' => 'Done'];
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('workspaces/views-nav', ['current' => 'cards', 'workspace' => $workspace]) ?>

<div class="board-tools">
    <form action="<?= $this->url('/workspaces/' . $workspace->publicId . '/cards') ?>" method="get" class="filter-form card" role="search">
        <label class="filter-q"><span class="visually-hidden"><?= $this->te('Search cards') ?></span>
            <input type="search" name="q" value="<?= $this->e($filter->q) ?>" maxlength="100" dir="auto" placeholder="<?= $this->te('Search cards') ?>"></label>
        <label><span class="visually-hidden"><?= $this->te('Board') ?></span>
            <select name="board">
                <option value=""><?= $this->te('Every board') ?></option>
                <?php foreach ($boards as $board): ?>
                    <option value="<?= $this->e($board->publicId) ?>" <?= $chosen === $board->publicId ? 'selected' : '' ?>><?= $this->e($board->name) ?></option>
                <?php endforeach; ?>
            </select></label>
        <label><span class="visually-hidden"><?= $this->te('Member') ?></span>
            <select name="member">
                <option value=""><?= $this->te('Anyone') ?></option>
                <option value="me" <?= $filter->member === 'me' ? 'selected' : '' ?>><?= $this->te('Assigned to me') ?></option>
                <option value="none" <?= $filter->member === 'none' ? 'selected' : '' ?>><?= $this->te('Nobody assigned') ?></option>
                <?php foreach ($people as $id => $person): ?>
                    <option value="<?= $id ?>" <?= $filter->member === (string) $id ? 'selected' : '' ?>><?= $this->e($person->name) ?></option>
                <?php endforeach; ?>
            </select></label>
        <label><span class="visually-hidden"><?= $this->te('Due date') ?></span>
            <select name="due">
                <?php foreach ($dueChoices as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $filter->due === $value ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                <?php endforeach; ?>
            </select></label>
        <label><span class="visually-hidden"><?= $this->te('Status') ?></span>
            <select name="status">
                <?php foreach ($statusChoices as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $filter->status === $value ? 'selected' : '' ?>><?= $this->te($label) ?></option>
                <?php endforeach; ?>
            </select></label>
        <button class="btn btn-primary" type="submit"><?= $this->icon('filter') ?> <?= $this->te('Filter') ?></button>
    </form>
</div>

<main class="wrap view-page">
    <?php if ($rows === []): ?>
        <p class="card muted center"><?= $this->te($total > 0 ? 'No card matches these filters.' : 'There are no open cards on these boards.') ?></p>
    <?php else: ?>
        <div class="table-wrap card">
            <table class="data-table">
                <caption class="visually-hidden"><?= $this->te('Cards on every board') ?></caption>
                <thead><tr>
                    <th scope="col"><?= $this->te('Card') ?></th>
                    <th scope="col"><?= $this->te('Board') ?></th>
                    <th scope="col"><?= $this->te('List') ?></th>
                    <th scope="col"><?= $this->te('Due') ?></th>
                    <th scope="col"><?= $this->te('Status') ?></th>
                    <th scope="col"><?= $this->te('People') ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($rows as $card): ?>
                        <?php $late = $card->isOverdue($today); ?>
                        <tr class="<?= $card->isComplete() ? 'is-done' : '' ?>">
                            <th scope="row"><a href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"><?= $this->e($card->title) ?></a></th>
                            <td dir="auto"><a href="<?= $this->url('/boards/' . ($boards[$card->boardId]->publicId ?? '')) ?>"><?= $this->e($boards[$card->boardId]->name ?? '') ?></a></td>
                            <td dir="auto"><?= $this->e($lists[$card->listId] ?? '') ?></td>
                            <td class="nowrap <?= $late ? 'danger' : '' ?>"><?= $this->e((string) $card->dueDate()) ?></td>
                            <td><span class="status status-<?= $card->isComplete() ? 'done' : ($late ? 'late' : 'open') ?>"><?= $this->te($card->isComplete() ? 'Done' : ($late ? 'Overdue' : 'Still open')) ?></span></td>
                            <td>
                                <?php foreach ($members[$card->id] ?? [] as $userId): ?>
                                    <?php if (isset($people[$userId])): ?><span class="avatar avatar-xs" title="<?= $this->e($people[$userId]->name) ?>"><?= $this->e(mb_strtoupper(mb_substr($people[$userId]->name, 0, 1))) ?></span><?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted small"><?= $this->te(':count cards', ['count' => (string) count($rows)]) ?></p>
    <?php endif; ?>
</main>
