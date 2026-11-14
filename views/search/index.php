<?php
/**
 * @var \App\View\View $this
 * @var string $q
 * @var bool $searching
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var list<\App\Modules\Board\Contract\BoardSummary> $boards
 * @var list<\App\Modules\Card\Contract\CardSummary> $cards
 * @var array<int, \App\Modules\Board\Contract\BoardSummary> $boardById
 * @var array<int, \App\Shared\Search\SearchHit> $hits card id => where it matched
 * @var list<\App\Modules\Board\Contract\BoardSummary> $boardChoices
 * @var string $only
 * @var bool $withArchive
 * @var \DateTimeImmutable $today
 */
?>
<?php
$places = [
    'description' => 'In the description',
    'comment'     => 'In a comment',
    'checklist'   => 'In a checklist',
    'attachment'  => 'In an attachment',
];
?>
<?= $this->partial('partials/app-header', ['searchQuery' => $q]) ?>
<main class="wrap narrow">
    <section class="card stack">
        <h1><?= $this->icon('search') ?> <?= $this->te('Search') ?></h1>
        <form action="<?= $this->url('/search') ?>" method="get" class="inline-form" role="search">
            <input type="search" name="q" value="<?= $this->e($q) ?>" maxlength="100" dir="auto" autofocus
                   aria-label="<?= $this->te('Search') ?>" placeholder="<?= $this->te('Search boards and cards in :workspace', ['workspace' => $workspace->name]) ?>">
            <button class="btn btn-primary" type="submit"><?= $this->te('Search') ?></button>
            <div class="search-options">
                <label class="visually-hidden" for="search-board"><?= $this->te('Board') ?></label>
                <select name="board" id="search-board">
                    <option value=""><?= $this->te('All boards') ?></option>
                    <?php foreach ($boardChoices as $choice): ?>
                        <option value="<?= $this->e($choice->publicId) ?>" <?= $choice->publicId === $only ? 'selected' : '' ?>><?= $this->e($choice->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="check"><input type="checkbox" name="archived" value="1" <?= $withArchive ? 'checked' : '' ?>> <?= $this->te('Include the archive') ?></label>
            </div>
        </form>
        <p class="muted small"><?= $this->te('Searches card titles, descriptions, comments, checklists and attachments on the boards you can see.') ?></p>

        <?php if ($q !== '' && !$searching): ?>
            <p class="muted"><?= $this->te('Type at least two letters.') ?></p>
        <?php elseif ($searching && !$boards && !$cards): ?>
            <p class="muted"><?= $this->te('Nothing matches :q.', ['q' => $q]) ?></p>
        <?php endif; ?>

        <?php if ($boards): ?>
            <h2 class="section-title"><?= $this->icon('board') ?> <?= $this->te('Boards') ?></h2>
            <ul class="search-results">
                <?php foreach ($boards as $board): ?>
                    <li><a href="<?= $this->url('/boards/' . $board->publicId) ?>" dir="auto"><?= $this->e($board->name) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($cards): ?>
            <h2 class="section-title"><?= $this->icon('checklist') ?> <?= $this->te('Cards') ?></h2>
            <ul class="search-results">
                <?php foreach ($cards as $card): ?>
                    <li class="<?= $card->isComplete() ? 'is-done' : '' ?>">
                        <a href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"><?= $this->e($card->title) ?></a>
                        <span class="muted small"><bdi><?= $this->e($boardById[$card->boardId]->name ?? '') ?></bdi><?php if ($card->archived): ?> · <?= $this->te('archived') ?><?php endif; ?></span>
                        <?php $hit = $hits[$card->id] ?? null; ?>
                        <?php if ($hit !== null && isset($places[$hit->where])): ?>
                            <span class="search-excerpt small"><span class="muted"><?= $this->te($places[$hit->where]) ?>:</span> <bdi dir="auto"><?= $this->e($hit->excerpt) ?></bdi></span>
                        <?php endif; ?>
                        <?php if ($card->dueDate()): ?>
                            <span class="due <?= $card->isOverdue($today) ? 'due-overdue' : '' ?>"><?= $this->icon('calendar') ?><?= $this->e($card->dueDate()) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
