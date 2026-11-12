<?php
/**
 * The filter bar on a board page. A plain GET form, so it works without
 * JavaScript and a filtered board can be bookmarked or shared.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Board\Contract\CardFilter $filter
 * @var list<\App\Modules\Label\Contract\Label> $boardLabels
 * @var list<int> $audience
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var int $totalCards
 * @var int $shownCards
 */
$base = '/boards/' . $board->publicId;
$dueChoices = ['' => 'Any due date', 'overdue' => 'Overdue', 'today' => 'Due today', 'week' => 'Due in the next 7 days', 'none' => 'No due date'];
$statusChoices = ['' => 'Open and done', 'open' => 'Open', 'done' => 'Done'];
?>
<details class="filters" <?= $filter->isActive() ? 'open' : '' ?>>
    <summary class="btn btn-ghost js-filter-toggle"><?= $this->icon('filter') ?><?= $this->te('Filter') ?>
        <?php if ($filter->isActive()): ?><span class="count"><?= $shownCards ?>/<?= $totalCards ?></span><?php endif; ?>
        <kbd class="hide-sm">F</kbd></summary>
    <form action="<?= $this->url($base) ?>" method="get" class="filter-form" role="search">
        <label class="filter-q"><span class="visually-hidden"><?= $this->te('Search cards') ?></span>
            <input type="search" name="q" value="<?= $this->e($filter->q) ?>" maxlength="100" dir="auto"
                   class="js-filter-search" placeholder="<?= $this->te('Search cards on this board') ?>"></label>
        <label><span class="visually-hidden"><?= $this->te('Label') ?></span>
            <select name="label">
                <option value=""><?= $this->te('Any label') ?></option>
                <?php foreach ($boardLabels as $label): ?>
                    <option value="<?= $label->id ?>" <?= $filter->label === $label->id ? 'selected' : '' ?>><?= $this->e($label->name) ?></option>
                <?php endforeach; ?>
            </select></label>
        <label><span class="visually-hidden"><?= $this->te('Member') ?></span>
            <select name="member">
                <option value=""><?= $this->te('Anyone') ?></option>
                <option value="me" <?= $filter->member === 'me' ? 'selected' : '' ?>><?= $this->te('Assigned to me') ?></option>
                <option value="none" <?= $filter->member === 'none' ? 'selected' : '' ?>><?= $this->te('Nobody assigned') ?></option>
                <?php foreach ($audience as $userId): ?>
                    <?php if (isset($people[$userId])): ?>
                        <option value="<?= $userId ?>" <?= $filter->member === (string) $userId ? 'selected' : '' ?>><?= $this->e($people[$userId]->name) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select></label>
        <label><span class="visually-hidden"><?= $this->te('Due date') ?></span>
            <select name="due">
                <?php foreach ($dueChoices as $value => $text): ?>
                    <option value="<?= $value ?>" <?= $filter->due === $value ? 'selected' : '' ?>><?= $this->te($text) ?></option>
                <?php endforeach; ?>
            </select></label>
        <label><span class="visually-hidden"><?= $this->te('Status') ?></span>
            <select name="status">
                <?php foreach ($statusChoices as $value => $text): ?>
                    <option value="<?= $value ?>" <?= $filter->status === $value ? 'selected' : '' ?>><?= $this->te($text) ?></option>
                <?php endforeach; ?>
            </select></label>
        <button class="btn btn-primary" type="submit"><?= $this->te('Apply') ?></button>
        <?php if ($filter->isActive()): ?>
            <a class="btn btn-ghost" href="<?= $this->url($base) ?>"><?= $this->te('Clear') ?></a>
        <?php endif; ?>
    </form>
</details>
<?php if ($filter->isActive()): ?>
    <p class="filter-note muted small" role="status"><?= $this->te('Showing :shown of :total cards.', ['shown' => $shownCards, 'total' => $totalCards]) ?></p>
<?php endif; ?>
