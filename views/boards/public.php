<?php
/**
 * A board seen through its public link: read-only, no account. Only lists,
 * card titles, labels and dates; never people, comments, files or history.
 *
 * @var \App\View\View $this
 * @var string $appName
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var list<\App\Modules\Board\Contract\ListSummary> $lists
 * @var array<int, list<\App\Modules\Card\Contract\CardSummary>> $cardsByList
 * @var array<int, list<\App\Modules\Label\Contract\Label>> $cardLabels
 */
?>
<div class="board-bar">
    <div class="board-bar-title">
        <h1 dir="auto"><?= $this->e($board->name) ?></h1>
        <span class="pill"><?= $this->icon('link') ?><?= $this->te('Read-only view shared by link') ?></span>
    </div>
    <div class="board-bar-actions"><span class="muted small"><?= $this->e($appName) ?></span></div>
</div>
<?php if ($board->description !== ''): ?>
    <p class="board-description muted" dir="auto"><?= nl2br($this->e($board->description)) ?></p>
<?php endif; ?>

<div class="board-layout">
    <main class="lists">
        <?php foreach ($lists as $list): $cards = $cardsByList[$list->id] ?? []; ?>
            <section class="list <?= $list->color ? 'list-color tile-' . $this->e($list->color) : '' ?>" aria-labelledby="public-list-<?= $list->id ?>">
                <header class="list-head">
                    <h2 id="public-list-<?= $list->id ?>" dir="auto"><?= $this->e($list->name) ?> <span class="count"><?= count($cards) ?></span></h2>
                </header>
                <div class="list-cards">
                    <?php foreach ($cards as $card): ?>
                        <article class="card-tile <?= $card->isComplete() ? 'is-done' : '' ?>">
                            <?php if ($labels = $cardLabels[$card->id] ?? []): ?>
                                <div class="tile-labels">
                                    <?php foreach ($labels as $label): ?>
                                        <span class="label label-<?= $this->e($label->color) ?>" dir="auto"><?= $this->e($label->name) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <p class="card-title" dir="auto"><?= $this->e($card->title) ?></p>
                            <?php if ($card->dueDate() !== null || $card->isComplete()): ?>
                                <div class="tile-foot">
                                    <?php if ($card->isComplete()): ?><span class="badge"><?= $this->icon('check') ?><?= $this->te('Done') ?></span><?php endif; ?>
                                    <?php if ($card->dueDate() !== null): ?><span class="due"><?= $this->icon('calendar') ?><time datetime="<?= $this->e((string) $card->dueDate()) ?>"><?= $this->e((string) $card->dueDate()) ?></time></span><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <?php if ($cards === []): ?><p class="muted small"><?= $this->te('No cards') ?></p><?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>
</div>
