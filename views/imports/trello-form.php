<?php
/**
 * Step 1 of a Trello import: choose the exported JSON file.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var list<array{publicId: string, kind: string, fileName: string, createdAt: string}> $pending
 */
$form = $this->form;
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow stack import-page">
    <a class="back-link" href="<?= $this->url('/boards') ?>"><?= $this->icon('left') ?> <span><?= $this->te('Boards') ?></span></a>
    <h1><?= $this->te('Import a Trello board') ?></h1>

    <section class="card stack">
        <p><?= $this->te('In Trello open the board, then Menu → Print, export and share → Export as JSON, and save the file. It becomes a new board in :workspace. You will see what is in it before anything is saved.', ['workspace' => $workspace->name]) ?></p>
        <?php if (!$workspace->role->canCreateBoards()): ?>
            <p class="muted"><?= $this->te('You can\'t create boards in this workspace.') ?></p>
        <?php else: ?>
            <form action="<?= $this->url('/import/trello') ?>" method="post" enctype="multipart/form-data" class="stack" novalidate>
                <?= $this->csrfField() ?>
                <label>
                    <span><?= $this->te('Trello JSON file (up to 20 MB)') ?></span>
                    <input type="file" name="file" accept=".json,application/json" required <?= $form->error('file') ? 'aria-invalid="true" aria-describedby="file-error"' : '' ?>>
                </label>
                <?= $this->partial('partials/field-error', ['field' => 'file']) ?>
                <button class="btn btn-primary" type="submit"><?= $this->icon('upload') ?><?= $this->te('Read the file') ?></button>
            </form>
        <?php endif; ?>
        <details class="small">
            <summary><?= $this->te('What comes across?') ?></summary>
            <ul>
                <li><?= $this->te('Open lists and cards in their order, with descriptions, start and due dates, and whether they are complete. Archived lists and cards are left out.') ?></li>
                <li><?= $this->te('Labels with their colors, checklists with ticked items, and comments with who wrote them and when.') ?></li>
                <li><?= $this->te('Members are matched by their full name to people of this workspace and added to the new board. Trello exports have no emails, so anyone not found is listed.') ?></li>
                <li><?= $this->te('Attachments become links. Files uploaded to Trello stay on Trello: opening them needs a Trello sign-in. Nothing is downloaded from Trello.') ?></li>
            </ul>
        </details>
    </section>

    <?= $this->partial('imports/pending', ['pending' => $pending]) ?>
</main>
