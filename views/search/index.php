<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\SearchQuery $query
 * @var list<\App\Modules\Card\Contract\CardSummary> $cards
 * @var int $total
 * @var array<int, \App\Shared\Search\SearchHit> $hits card id => where it matched
 * @var list<\App\Modules\Board\Contract\BoardSummary> $boards boards whose name matches
 * @var array<int, \App\Modules\Board\Contract\BoardSummary> $boardById
 * @var array<int, \App\Modules\Workspace\Contract\WorkspaceSummary> $workspaceOf board id => workspace
 * @var list<\App\Modules\Workspace\Contract\WorkspaceSummary> $workspaces
 * @var list<\App\Modules\Board\Contract\BoardSummary> $boardChoices
 * @var list<\App\Modules\Board\Contract\ListSummary> $listChoices
 * @var array<int, list<\App\Modules\Label\Contract\Label>> $cardLabels
 * @var array<int, list<int>> $cardMembers
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var list<array{id: int, public_id: string, name: string, query: string, updated_at: string}> $saved
 * @var string $shareLink
 * @var \DateTimeImmutable $today
 */
$places = [
    'description' => 'In the description',
    'comment'     => 'In a comment',
    'checklist'   => 'In a checklist',
    'attachment'  => 'In an attachment',
    'field'       => 'In a custom field',
];
$q = $query->query();
$value = static fn (string $key): string => $q[$key] ?? '';
$form = $this->form;
$searching = $query->isActive();
$queryString = $query->queryString();
$currentSaved = null;
foreach ($saved as $s) {
    if ($s['query'] === $queryString && $queryString !== '') {
        $currentSaved = $s;
    }
}
?>
<?= $this->partial('partials/app-header', ['searchQuery' => $query->q]) ?>
<main class="wrap search-page">
    <section class="card stack search-main">
        <h1><?= $this->icon('search') ?> <?= $this->te('Search') ?></h1>
        <form action="<?= $this->url('/search') ?>" method="get" class="stack" role="search">
            <div class="inline-form">
                <input type="search" name="q" value="<?= $this->e($query->q) ?>" maxlength="100" dir="auto" autofocus
                       aria-label="<?= $this->te('Search') ?>" placeholder="<?= $this->te('Search cards and boards in all your workspaces') ?>">
                <button class="btn btn-primary" type="submit"><?= $this->te('Search') ?></button>
            </div>
            <details class="search-filters" <?= $query->hasCardFilters() || $query->workspace !== '' || $query->board !== '' || $query->archived ? 'open' : '' ?>>
                <summary><?= $this->icon('filter') ?> <?= $this->te('Filters') ?></summary>
                <div class="filter-grid">
                    <label><span><?= $this->te('Workspace') ?></span>
                        <select name="workspace">
                            <option value=""><?= $this->te('All workspaces') ?></option>
                            <?php foreach ($workspaces as $workspace): ?>
                                <option value="<?= $this->e($workspace->publicId) ?>" <?= $workspace->publicId === $query->workspace ? 'selected' : '' ?>><?= $this->e($workspace->name) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    <label><span><?= $this->te('Board') ?></span>
                        <select name="board">
                            <option value=""><?= $this->te('All boards') ?></option>
                            <?php foreach ($boardChoices as $choice): ?>
                                <option value="<?= $this->e($choice->publicId) ?>" <?= $choice->publicId === $query->board ? 'selected' : '' ?>><?= $this->e($choice->name) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    <?php if ($listChoices): ?>
                        <label><span><?= $this->te('List') ?></span>
                            <select name="list">
                                <option value=""><?= $this->te('All lists') ?></option>
                                <?php foreach ($listChoices as $list): ?>
                                    <option value="<?= $list->id ?>" <?= $list->id === $query->list ? 'selected' : '' ?>><?= $this->e($list->name) ?></option>
                                <?php endforeach; ?>
                            </select></label>
                    <?php endif; ?>
                    <label><span><?= $this->te('Member') ?></span>
                        <select name="member">
                            <option value=""><?= $this->te('Anyone') ?></option>
                            <option value="me" <?= $value('member') === 'me' ? 'selected' : '' ?>><?= $this->te('Cards assigned to me') ?></option>
                            <option value="none" <?= $value('member') === 'none' ? 'selected' : '' ?>><?= $this->te('No members') ?></option>
                            <?php foreach ($people as $person): ?>
                                <option value="<?= $person->id ?>" <?= $value('member') === (string) $person->id ? 'selected' : '' ?>><?= $this->e($person->name) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    <label><span><?= $this->te('Label name') ?></span>
                        <input name="label" maxlength="50" dir="auto" value="<?= $this->e($query->label) ?>"></label>
                    <label><span><?= $this->te('Due') ?></span>
                        <select name="due">
                            <option value=""><?= $this->te('Any time') ?></option>
                            <?php foreach (['overdue' => 'Overdue', 'today' => 'Due today', 'week' => 'Due in the next 7 days', 'none' => 'No due date'] as $key => $text): ?>
                                <option value="<?= $key ?>" <?= $value('due') === $key ? 'selected' : '' ?>><?= $this->te($text) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    <label><span><?= $this->te('Status') ?></span>
                        <select name="status">
                            <option value=""><?= $this->te('Open and done') ?></option>
                            <option value="open" <?= $value('status') === 'open' ? 'selected' : '' ?>><?= $this->te('Open') ?></option>
                            <option value="done" <?= $value('status') === 'done' ? 'selected' : '' ?>><?= $this->te('Done') ?></option>
                        </select></label>
                    <label class="check"><input type="checkbox" name="archived" value="1" <?= $query->archived ? 'checked' : '' ?>> <?= $this->te('Include the archive') ?></label>
                </div>
                <div class="filter-actions">
                    <button class="btn btn-ghost" type="submit"><?= $this->te('Apply filters') ?></button>
                    <a class="btn btn-ghost" href="<?= $this->url('/search') ?>"><?= $this->te('Clear') ?></a>
                </div>
            </details>
        </form>
        <p class="muted small"><?= $this->te('Searches card titles, descriptions, comments, checklists and attachments on the boards you can see. Filters alone (without text) look at open cards.') ?></p>

        <?php if ($query->q !== '' && !$query->hasText() && !$query->hasCardFilters()): ?>
            <p class="muted"><?= $this->te('Type at least two letters.') ?></p>
        <?php elseif ($searching && !$boards && !$cards): ?>
            <p class="empty-note"><?= $query->q !== '' ? $this->te('Nothing matches :q.', ['q' => $query->q]) : $this->te('No cards match these filters.') ?></p>
        <?php endif; ?>

        <?php if ($boards): ?>
            <h2 class="section-title"><?= $this->icon('board') ?> <?= $this->te('Boards') ?></h2>
            <ul class="search-results">
                <?php foreach ($boards as $board): ?>
                    <li><a href="<?= $this->url('/boards/' . $board->publicId) ?>" dir="auto"><?= $this->e($board->name) ?></a>
                        <span class="muted small"><bdi><?= $this->e($workspaceOf[$board->id]->name ?? '') ?></bdi></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($cards): ?>
            <h2 class="section-title"><?= $this->icon('checklist') ?> <?= $this->te('Cards') ?>
                <span class="count"><?= $total > count($cards) ? $this->te(':shown of :total', ['shown' => (string) count($cards), 'total' => (string) $total]) : count($cards) ?></span></h2>
            <ul class="search-results">
                <?php foreach ($cards as $card): ?>
                    <li class="<?= $card->isComplete() ? 'is-done' : '' ?>">
                        <a class="result-title" href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"><?= $this->e($card->title) ?></a>
                        <span class="muted small"><bdi><?= $this->e($workspaceOf[$card->boardId]->name ?? '') ?></bdi> · <bdi><?= $this->e($boardById[$card->boardId]->name ?? '') ?></bdi><?php if ($card->archived): ?> · <?= $this->te('archived') ?><?php endif; ?></span>
                        <?php $hit = $hits[$card->id] ?? null; ?>
                        <?php if ($hit !== null && isset($places[$hit->where])): ?>
                            <span class="search-excerpt small"><span class="muted"><?= $this->te($places[$hit->where]) ?>:</span> <bdi dir="auto"><?= $this->e($hit->excerpt) ?></bdi></span>
                        <?php endif; ?>
                        <span class="search-meta">
                            <?php foreach ($cardLabels[$card->id] ?? [] as $label): ?>
                                <span class="label label-<?= $this->e($label->color) ?>" dir="auto"><?= $this->e($label->name) ?></span>
                            <?php endforeach; ?>
                            <?php if ($card->dueDate()): ?>
                                <span class="due <?= $card->isOverdue($today) ? 'due-overdue' : '' ?>"><?= $this->icon('calendar') ?><?= $this->e($card->dueDate()) ?></span>
                            <?php endif; ?>
                            <?php foreach ($cardMembers[$card->id] ?? [] as $memberId): if (!isset($people[$memberId])) continue; ?>
                                <span class="avatar avatar-xs" title="<?= $this->e($people[$memberId]->name) ?>"><?= $this->e($people[$memberId]->initial()) ?></span>
                            <?php endforeach; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <aside class="card stack search-side" aria-labelledby="saved-title">
        <h2 id="saved-title" class="section-title"><?= $this->icon('star') ?> <?= $this->te('Saved searches') ?></h2>
        <?php if (!$saved): ?>
            <p class="muted small"><?= $this->te('Save a search to come back to it in one click. Only you see your saved searches.') ?></p>
        <?php else: ?>
            <ul class="saved-searches">
                <?php foreach ($saved as $s): ?>
                    <li class="<?= $currentSaved !== null && $currentSaved['id'] === $s['id'] ? 'is-current' : '' ?>">
                        <a href="<?= $this->url('/search' . ($s['query'] !== '' ? '?' . $s['query'] : '')) ?>" dir="auto"><?= $this->e($s['name']) ?></a>
                        <details class="menu menu-end">
                            <summary class="icon-btn" title="<?= $this->te('Edit saved search') ?>"><?= $this->icon('more') ?><span class="visually-hidden"><?= $this->te('Edit saved search') ?></span></summary>
                            <div class="menu-panel stack">
                                <form action="<?= $this->url('/search/saved/' . $s['public_id']) ?>" method="post" class="stack">
                                    <?= $this->csrfField() ?>
                                    <label><span><?= $this->te('Name') ?></span>
                                        <input name="name" required maxlength="80" dir="auto" value="<?= $this->e($s['name']) ?>"></label>
                                    <?php if ($searching && $s['query'] !== $queryString): ?>
                                        <label class="check"><input type="checkbox" name="query" value="<?= $this->e($queryString) ?>"> <?= $this->te('Replace its filters with the current search') ?></label>
                                    <?php endif; ?>
                                    <button class="btn btn-ghost" type="submit"><?= $this->te('Save') ?></button>
                                </form>
                                <form action="<?= $this->url('/search/saved/' . $s['public_id'] . '/delete') ?>" method="post"
                                      data-confirm="<?= $this->te('Delete the saved search “:name”?', ['name' => $s['name']]) ?>">
                                    <?= $this->csrfField() ?>
                                    <button class="menu-item danger" type="submit"><?= $this->icon('trash') ?><?= $this->te('Delete') ?></button>
                                </form>
                            </div>
                        </details>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($searching && $currentSaved === null): ?>
            <form action="<?= $this->url('/search/saved') ?>" method="post" class="stack">
                <?= $this->csrfField() ?>
                <input type="hidden" name="query" value="<?= $this->e($queryString) ?>">
                <label><span><?= $this->te('Save this search as') ?></span>
                    <input name="name" required maxlength="80" dir="auto" value="<?= $this->e($form->old('name', $query->q)) ?>"
                           aria-invalid="<?= $form->error('name') ? 'true' : 'false' ?>" aria-describedby="name-error"></label>
                <?= $this->partial('partials/field-error', ['field' => 'name']) ?>
                <button class="btn btn-primary" type="submit"><?= $this->icon('star') ?><?= $this->te('Save search') ?></button>
            </form>
        <?php endif; ?>

        <?php if ($searching): ?>
            <div class="stack-sm">
                <label for="share-link"><span class="small"><?= $this->te('Link to this search') ?></span></label>
                <div class="copy-row">
                    <input id="share-link" class="js-copy-source" readonly dir="ltr" value="<?= $this->e($shareLink) ?>">
                    <button class="btn btn-ghost js-copy" type="button" data-copied="<?= $this->te('Copied') ?>"><?= $this->icon('copy') ?><span><?= $this->te('Copy') ?></span></button>
                </div>
                <p class="muted small"><?= $this->te('Anyone who opens it sees only the cards they themselves can see.') ?></p>
            </div>
        <?php endif; ?>
    </aside>
</main>
