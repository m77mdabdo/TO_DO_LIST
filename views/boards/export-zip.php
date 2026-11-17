<?php
/**
 * Before a ZIP export: how big it will be.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var array{cards: int, files: int, bytes: int, tooBig: bool} $preview
 * @var int $maxBytes
 * @var bool $available the PHP zip extension is installed
 */
$size = static function (int $bytes): string {
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) ceil($bytes / 1024)) . ' KB';
};
$base = '/boards/' . $board->publicId;
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow stack">
    <a class="back-link" href="<?= $this->url($base) ?>"><?= $this->icon('left') ?> <span dir="auto"><?= $this->e($board->name) ?></span></a>
    <h1><?= $this->te('Export with files') ?></h1>
    <section class="card stack">
        <p><?= $this->te('One ZIP file with board.json (lists, cards, labels, checklists, comments) and every attached file, in a folder per card.') ?></p>
        <dl class="account-facts">
            <dt><?= $this->te('Cards') ?></dt><dd><?= $preview['cards'] ?></dd>
            <dt><?= $this->te('Files') ?></dt><dd><?= $preview['files'] ?></dd>
            <dt><?= $this->te('Size of the files') ?></dt><dd dir="ltr"><?= $this->e($size($preview['bytes'])) ?></dd>
        </dl>
        <?php if (!$available): ?>
            <p class="alert"><?= $this->te('The PHP zip extension is not installed on this server. Use the JSON export, or ask whoever runs the app to turn on the zip extension.') ?></p>
        <?php elseif ($preview['tooBig']): ?>
            <p class="alert"><?= $this->te('This board\'s files are larger than the export limit (:limit). Download the JSON export, or ask whoever runs the app to raise EXPORT_ZIP_MAX_MB.', ['limit' => $size($maxBytes)]) ?></p>
        <?php else: ?>
            <form action="<?= $this->url($base . '/export.zip') ?>" method="post">
                <?= $this->csrfField() ?>
                <button class="btn btn-primary" type="submit"><?= $this->icon('download') ?><?= $this->te('Download the ZIP') ?></button>
            </form>
            <p class="muted small"><?= $this->te('Links are listed in board.json; only uploaded files are copied.') ?></p>
        <?php endif; ?>
    </section>
</main>
