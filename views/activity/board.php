<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var list<\App\Modules\Activity\Contract\ActivityEntry> $entries
 * @var bool $hasMore
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 */
$base = '/boards/' . $board->publicId;
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow">
    <a class="back-link" href="<?= $this->url($base) ?>"><?= $this->icon('left') ?> <span dir="auto"><?= $this->e($board->name) ?></span></a>

    <section class="card stack">
        <h1><?= $this->icon('activity') ?> <?= $this->te('Activity') ?></h1>
        <?php if (!$entries): ?>
            <p class="muted"><?= $this->te('Nothing has happened here yet.') ?></p>
        <?php endif; ?>
        <ul class="activity-list">
            <?php foreach ($entries as $entry): ?>
                <?= $this->partial('partials/activity-item', ['entry' => $entry, 'people' => $people]) ?>
            <?php endforeach; ?>
        </ul>
        <?php if ($hasMore): ?>
            <a class="btn btn-ghost" href="<?= $this->url($base . '/activity?before=' . end($entries)->id) ?>"><?= $this->te('Older activity') ?></a>
        <?php endif; ?>
    </section>
</main>
