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
 * @var list<\App\Modules\Field\Contract\CustomField> $fieldColumns added by the Field module
 * @var array<int, array<int, string>> $fieldValues card id => field id => stored value
 * @var \App\Modules\Board\Contract\TableLayout $layout this person's saved settings
 * @var list<array{title: ?string, color: ?string, rows: list<\App\Modules\Card\Contract\CardSummary>}> $groups
 */
use App\Modules\Board\Contract\TableLayout;

$progress ??= [];
$fieldColumns ??= [];
$fieldValues ??= [];
$base = '/boards/' . $board->publicId . '/table';
$fields = [];
foreach ($fieldColumns as $field) {
    $fields['field:' . $field->id] = $field;
}
$shown = $layout->shown(array_keys($fields));
$headers = TableLayout::COLUMNS;
$link = function (string $column) use ($base, $query, $sort, $desc): string {
    $dir = $column === $sort && !$desc ? 'desc' : 'asc';

    return $this->url($base . '?' . http_build_query($query + ['sort' => $column, 'dir' => $dir]));
};
// Every column for the settings form: the shown ones in their order, then the rest.
$allColumns = array_values(array_unique([...$shown, ...array_keys($headers), ...array_keys($fields)]));
$columnName = fn (string $key): string => isset($fields[$key]) ? $fields[$key]->name : $this->t($headers[$key] ?? $key);
$span = count($shown) + 1;
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('boards/view-bar', ['board' => $board, 'role' => $role, 'current' => '/table', 'query' => $query]) ?>

<div class="board-tools">
    <?= $this->partial('boards/filters', compact('board', 'filter', 'boardLabels', 'audience', 'people', 'totalCards', 'shownCards', 'viewPath')) ?>
    <details class="menu table-settings">
        <summary class="btn btn-ghost"><?= $this->icon('table') ?><?= $this->te('Table settings') ?><?php if ($layout->saved): ?> <span class="badge"><?= $this->te('Saved') ?></span><?php endif; ?></summary>
        <div class="menu-panel stack-sm">
            <form action="<?= $this->url($base . '/settings') ?>" method="post" class="stack-sm">
                <?= $this->csrfField() ?>
                <fieldset class="stack-sm">
                    <legend><?= $this->te('Columns to show, in order') ?></legend>
                    <ol class="column-choices js-column-choices">
                        <?php foreach ($allColumns as $key): ?>
                            <li class="column-choice">
                                <label class="check"><input type="checkbox" name="columns[]" value="<?= $this->e($key) ?>" <?= in_array($key, $shown, true) ? 'checked' : '' ?>> <span dir="auto"><?= $this->e($columnName($key)) ?></span></label>
                                <span class="row-actions">
                                    <button class="icon-btn js-column-up" type="button" title="<?= $this->te('Move up') ?>" aria-label="<?= $this->te('Move up') ?>: <?= $this->e($columnName($key)) ?>"><?= $this->icon('up') ?></button>
                                    <button class="icon-btn js-column-down" type="button" title="<?= $this->te('Move down') ?>" aria-label="<?= $this->te('Move down') ?>: <?= $this->e($columnName($key)) ?>"><?= $this->icon('down') ?></button>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <small class="muted"><?= $this->te('The card title is always the first column.') ?></small>
                </fieldset>
                <div class="field-row">
                    <label><span><?= $this->te('Sort by') ?></span>
                        <select name="sort">
                            <?php foreach (TableLayout::SORTS as $value => $label): ?><option value="<?= $value ?>" <?= $layout->sort === $value ? 'selected' : '' ?>><?= $this->te($label) ?></option><?php endforeach; ?>
                        </select></label>
                    <label><span><?= $this->te('Order') ?></span>
                        <select name="dir">
                            <option value="asc"><?= $this->te('Ascending (earliest, A to Z)') ?></option>
                            <option value="desc" <?= $layout->desc ? 'selected' : '' ?>><?= $this->te('Descending (latest, Z to A)') ?></option>
                        </select></label>
                    <label><span><?= $this->te('Group by') ?></span>
                        <select name="group">
                            <?php foreach (TableLayout::GROUPS as $value => $label): ?><option value="<?= $value ?>" <?= $layout->group === $value ? 'selected' : '' ?>><?= $this->te($label) ?></option><?php endforeach; ?>
                        </select></label>
                </div>
                <?php foreach (['columns', 'sort', 'group'] as $field): ?><?= $this->partial('partials/field-error', ['field' => $field]) ?><?php endforeach; ?>
                <small class="muted"><?= $this->te('Saved for you only. Other members keep their own table.') ?></small>
                <div class="row-actions">
                    <button class="btn btn-primary" type="submit"><?= $this->te('Save my view') ?></button>
                    <?php if ($layout->saved): ?><button class="btn btn-ghost" type="submit" name="reset" value="1"><?= $this->te('Reset') ?></button><?php endif; ?>
                </div>
            </form>
        </div>
    </details>
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
                        <th scope="col" <?= $sort === 'title' ? 'aria-sort="' . ($desc ? 'descending' : 'ascending') . '"' : '' ?>>
                            <a href="<?= $this->e($link('title')) ?>"><?= $this->te('Card') ?><?php if ($sort === 'title'): ?> <span aria-hidden="true"><?= $desc ? '↓' : '↑' ?></span><?php endif; ?></a>
                        </th>
                        <?php foreach ($shown as $column): ?>
                            <?php if (isset(TableLayout::SORTS[$column])): ?>
                                <th scope="col" <?= $column === $sort ? 'aria-sort="' . ($desc ? 'descending' : 'ascending') . '"' : '' ?>>
                                    <a href="<?= $this->e($link($column)) ?>"><?= $this->e($columnName($column)) ?><?php if ($column === $sort): ?> <span aria-hidden="true"><?= $desc ? '↓' : '↑' ?></span><?php endif; ?></a>
                                </th>
                            <?php else: ?>
                                <th scope="col" dir="auto"><?= $this->e($columnName($column)) ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <?php foreach ($groups as $section): ?>
                    <tbody>
                        <?php if ($section['title'] !== null): ?>
                            <tr class="group-row">
                                <th scope="rowgroup" colspan="<?= $span ?>">
                                    <?php if ($section['title'] === ''): ?>
                                        <?= $this->te($layout->group === 'member' ? 'Nobody assigned' : 'No label') ?>
                                    <?php elseif ($section['color'] !== null): ?>
                                        <span class="label label-<?= $this->e($section['color']) ?>" dir="auto"><?= $this->e($section['title']) ?></span>
                                    <?php else: ?>
                                        <span dir="auto"><?= $this->e($section['title']) ?></span>
                                    <?php endif; ?>
                                    <span class="count"><?= count($section['rows']) ?></span>
                                </th>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($section['rows'] as $card): ?>
                            <?php $late = $card->isOverdue($today); ?>
                            <tr class="<?= $card->isComplete() ? 'is-done' : '' ?>">
                                <th scope="row"><a href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"><?= $this->e($card->title) ?></a></th>
                                <?php foreach ($shown as $column): ?>
                                    <?php switch ($column):
                                        case 'list': ?><td dir="auto"><?= $this->e($listName[$card->listId] ?? '') ?></td><?php break;
                                        case 'start': ?><td class="nowrap"><bdi><?= $this->e((string) $card->startDate()) ?></bdi></td><?php break;
                                        case 'due': ?><td class="nowrap <?= $late ? 'danger' : '' ?>"><bdi><?= $this->e((string) $card->dueDate()) ?></bdi></td><?php break;
                                        case 'status': ?><td><span class="status status-<?= $card->isComplete() ? 'done' : ($late ? 'late' : 'open') ?>"><?= $this->te($card->isComplete() ? 'Done' : ($late ? 'Overdue' : 'Still open')) ?></span></td><?php break;
                                        case 'labels': ?><td>
                                            <?php foreach ($cardLabels[$card->id] ?? [] as $label): ?>
                                                <span class="label label-<?= $this->e($label->color) ?>" dir="auto"><?= $this->e($label->name) ?></span>
                                            <?php endforeach; ?>
                                        </td><?php break;
                                        case 'people': ?><td>
                                            <?php foreach ($cardMembers[$card->id] ?? [] as $userId): ?>
                                                <?php if (isset($people[$userId])): ?><span class="avatar avatar-xs" title="<?= $this->e($people[$userId]->name) ?>"><?= $this->e(mb_strtoupper(mb_substr($people[$userId]->name, 0, 1))) ?></span><?php endif; ?>
                                            <?php endforeach; ?>
                                        </td><?php break;
                                        case 'checklist': ?><td class="nowrap"><?php if (isset($progress[$card->id])): ?><?= $progress[$card->id]['done'] ?>/<?= $progress[$card->id]['total'] ?><?php endif; ?></td><?php break;
                                        default: $field = $fields[$column]; ?>
                                            <td dir="auto" class="<?= $field->type === 'number' || $field->type === 'date' ? 'nowrap' : '' ?>"><?= $this->e($field->display($fieldValues[$card->id][$field->id] ?? null)) ?></td>
                                    <?php endswitch; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endforeach; ?>
            </table>
        </div>
        <p class="muted small"><?= $this->te(':count cards', ['count' => (string) count($rows)]) ?></p>
    <?php endif; ?>
</main>
