<?php
/**
 * Step 1 of a CSV import: choose the file.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var list<array{publicId: string, kind: string, fileName: string, createdAt: string}> $pending
 * @var array<string, string> $fields
 */
$form = $this->form;
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow stack import-page">
    <a class="back-link" href="<?= $this->url('/boards/' . $board->publicId) ?>"><?= $this->icon('left') ?> <span dir="auto"><?= $this->e($board->name) ?></span></a>
    <h1><?= $this->te('Import cards from a CSV file') ?></h1>

    <section class="card stack">
        <p><?= $this->te('Export a sheet from Excel, Google Sheets or another tool as CSV. The first row must name the columns. You will see every row before anything is saved, and can cancel.') ?></p>
        <form action="<?= $this->url('/boards/' . $board->publicId . '/import') ?>" method="post" enctype="multipart/form-data" class="stack" novalidate>
            <?= $this->csrfField() ?>
            <label>
                <span><?= $this->te('CSV file (up to 2 MB and 2000 rows)') ?></span>
                <input type="file" name="file" accept=".csv,text/csv,text/plain" required <?= $form->error('file') ? 'aria-invalid="true" aria-describedby="file-error"' : '' ?>>
            </label>
            <?= $this->partial('partials/field-error', ['field' => 'file']) ?>
            <button class="btn btn-primary" type="submit"><?= $this->icon('upload') ?><?= $this->te('Read the file') ?></button>
        </form>
        <details class="small">
            <summary><?= $this->te('Which columns can it read?') ?></summary>
            <ul>
                <?php foreach ($fields as $field => $label): ?>
                    <li><strong><?= $this->te($label) ?></strong>: <?= $this->te([
                        'title'       => 'the card title (required).',
                        'description' => 'any text.',
                        'list'        => 'the list name; missing lists can be created. Empty means the first list.',
                        'labels'      => 'label names separated by commas.',
                        'start_date'  => '2026-01-31 or 31/01/2026.',
                        'due_date'    => '2026-01-31 or 31/01/2026.',
                        'members'     => 'emails or names of people who can open this board, separated by commas.',
                        'complete'    => 'yes, 1, x or done marks the card complete.',
                    ][$field]) ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
    </section>

    <?= $this->partial('imports/pending', ['pending' => $pending]) ?>
</main>
