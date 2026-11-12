<?php
/**
 * @var \App\View\View $this
 * @var string $q
 * @var bool $searching
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var list<\App\Modules\Board\Contract\BoardSummary> $boards
 * @var list<\App\Modules\Card\Contract\CardSummary> $cards
 * @var array<int, \App\Modules\Board\Contract\BoardSummary> $boardById
 * @var \DateTimeImmutable $today
 */
?>
<?= $this->partial('partials/app-header', ['searchQuery' => $q]) ?>
<main class="wrap narrow">
    <section class="card stack">
        <h1><?= $this->icon('search') ?> <?= $this->te('Search') ?></h1>
        <form action="<?= $this->url('/search') ?>" method="get" class="inline-form" role="search">
            <input type="search" name="q" value="<?= $this->e($q) ?>" maxlength="100" dir="auto" autofocus
                   aria-label="<?= $this->te('Search') ?>" placeholder="<?= $this->te('Search boards and cards in :workspace', ['workspace' => $workspace->name]) ?>">
            <button class="btn btn-primary" type="submit"><?= $this->te('Search') ?></button>
        </form>

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
                        <span class="muted small"><bdi><?= $this->e($boardById[$card->boardId]->name ?? '') ?></bdi></span>
                        <?php if ($card->dueDate()): ?>
                            <span class="due <?= $card->isOverdue($today) ? 'due-overdue' : '' ?>"><?= $this->icon('calendar') ?><?= $this->e($card->dueDate()) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
