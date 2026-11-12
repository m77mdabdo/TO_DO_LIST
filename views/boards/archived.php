<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Access\Contract\BoardRole $role
 * @var list<\App\Modules\Board\Contract\ListSummary> $lists
 * @var list<\App\Modules\Card\Contract\CardSummary> $cards
 */
use App\Modules\Access\Contract\Permission;

$canRestore = !$board->archived && $role->allows(Permission::ArchiveItems);
$canDelete = $role->allows(Permission::DeleteItems);
$base = '/boards/' . $board->publicId;
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow">
    <a class="back-link" href="<?= $this->url($base) ?>"><?= $this->icon('left') ?> <span dir="auto"><?= $this->e($board->name) ?></span></a>

    <section class="card stack">
        <h1><?= $this->icon('archive') ?> <?= $this->te('Archived items') ?></h1>

        <h2 class="section-title"><?= $this->te('Lists') ?></h2>
        <?php if (!$lists): ?><p class="muted small"><?= $this->te('No archived lists.') ?></p><?php endif; ?>
        <ul class="archived-list">
            <?php foreach ($lists as $list): ?>
                <li>
                    <span dir="auto"><?= $this->e($list->name) ?></span>
                    <?php if ($canRestore): ?>
                        <form action="<?= $this->url($base . '/lists/' . $list->id . '/restore') ?>" method="post">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-ghost" type="submit"><?= $this->icon('restore') ?><?= $this->te('Restore') ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <h2 class="section-title"><?= $this->te('Cards') ?></h2>
        <?php if (!$cards): ?><p class="muted small"><?= $this->te('No archived cards.') ?></p><?php endif; ?>
        <ul class="archived-list">
            <?php foreach ($cards as $card): ?>
                <li>
                    <a href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"><?= $this->e($card->title) ?></a>
                    <span class="row-actions">
                        <?php if ($canRestore): ?>
                            <form action="<?= $this->url('/cards/' . $card->publicId . '/restore') ?>" method="post">
                                <?= $this->csrfField() ?>
                                <button class="btn btn-ghost" type="submit"><?= $this->icon('restore') ?><?= $this->te('Restore') ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <form action="<?= $this->url('/cards/' . $card->publicId . '/delete') ?>" method="post"
                                  data-confirm="<?= $this->te('Delete this card for good? This cannot be undone.') ?>">
                                <?= $this->csrfField() ?>
                                <button class="btn btn-danger-ghost" type="submit"><?= $this->icon('trash') ?><?= $this->te('Delete') ?></button>
                            </form>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</main>
