<?php
/**
 * Imports the person started and didn't finish.
 *
 * @var \App\View\View $this
 * @var list<array{publicId: string, kind: string, fileName: string, createdAt: string}> $pending
 */
if ($pending === []) {
    return;
}
?>
<section class="card stack" aria-labelledby="pending-title">
    <h2 id="pending-title" class="section-title"><?= $this->icon('clock') ?> <?= $this->te('Waiting for you') ?></h2>
    <ul class="plain-list">
        <?php foreach ($pending as $item): ?>
            <li class="inline-form">
                <a href="<?= $this->url('/imports/' . $item['publicId']) ?>" dir="auto"><?= $this->e($item['fileName']) ?></a>
                <span class="muted small"><?= $this->e(substr($item['createdAt'], 0, 16)) ?></span>
                <form action="<?= $this->url('/imports/' . $item['publicId'] . '/cancel') ?>" method="post">
                    <?= $this->csrfField() ?>
                    <button class="btn btn-ghost" type="submit"><?= $this->te('Cancel') ?></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="muted small"><?= $this->te('Unfinished imports are thrown away after a day.') ?></p>
</section>
