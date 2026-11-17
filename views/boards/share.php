<?php
/**
 * Sharing a board read-only through a secret link (board managers).
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var bool $allowed the workspace rules allow public links
 * @var array{createdAt: string, createdBy: ?int}|null $status
 * @var ?string $fresh the new link, shown only right after it is made
 */
$base = '/boards/' . $board->publicId;
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow stack">
    <a class="back-link" href="<?= $this->url($base) ?>"><?= $this->icon('left') ?> <span dir="auto"><?= $this->e($board->name) ?></span></a>
    <h1><?= $this->te('Share by public link') ?></h1>

    <section class="card stack" id="public-link">
        <p><?= $this->te('Anyone with the link can view the lists and cards of this board without an account. They can\'t change anything, and they don\'t see members, comments, attachments or history.') ?></p>
        <?= $this->partial('partials/field-error', ['field' => 'public_link']) ?>

        <?php if ($fresh !== null): ?>
            <div class="invite-link stack" role="status">
                <p class="small"><?= $this->te('Copy the link now. It is shown only once; make a new one if you lose it.') ?></p>
                <div class="copy-row">
                    <input type="text" readonly value="<?= $this->e($fresh) ?>" class="js-copy-source" dir="ltr" aria-label="<?= $this->te('Public link') ?>">
                    <button type="button" class="btn btn-ghost js-copy" data-copied="<?= $this->te('Copied') ?>"><?= $this->icon('link') ?> <?= $this->te('Copy') ?></button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$allowed): ?>
            <p class="alert"><?= $this->te('The workspace rules don\'t allow public links. An owner can change that on the members page.') ?></p>
        <?php elseif ($board->archived): ?>
            <p class="alert"><?= $this->te('Restore the board first.') ?></p>
        <?php else: ?>
            <?php if ($status !== null): ?>
                <p class="muted small"><?= $this->te('A public link is on since :date.', ['date' => substr($status['createdAt'], 0, 16)]) ?></p>
            <?php endif; ?>
            <div class="form-actions">
                <form action="<?= $this->url($base . '/share') ?>" method="post"
                      <?= $status !== null ? 'data-confirm="' . $this->te('Make a new link? The current one stops working.') . '"' : '' ?>>
                    <?= $this->csrfField() ?>
                    <button class="btn btn-primary" type="submit"><?= $this->icon('link') ?><?= $this->te($status !== null ? 'Make a new link' : 'Make a public link') ?></button>
                </form>
                <?php if ($status !== null): ?>
                    <form action="<?= $this->url($base . '/share/delete') ?>" method="post">
                        <?= $this->csrfField() ?>
                        <button class="btn btn-danger-ghost" type="submit"><?= $this->te('Turn the link off') ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
