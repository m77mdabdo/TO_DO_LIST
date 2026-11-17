<?php
/**
 * Step 2 of a Trello import: what is in the file, and the new board's name.
 *
 * @var \App\View\View $this
 * @var array<string, mixed> $import
 * @var array{name: string, lists: int, cards: int, labels: int, checklists: int, items: int, comments: int, links: int, uploads: int, skippedLists: int, skippedCards: int, matched: list<string>, unmatched: list<string>, sample: list<array<string, mixed>>, importedAt: ?string} $preview
 */
$form = $this->form;
$action = $this->url('/imports/' . $import['publicId']);
$counts = [
    'Lists' => $preview['lists'], 'Cards' => $preview['cards'], 'Labels' => $preview['labels'], 'Checklists' => $preview['checklists'],
    'Checklist items' => $preview['items'], 'Comments' => $preview['comments'], 'Attachments (as links)' => $preview['links'],
];
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap stack import-page">
    <a class="back-link" href="<?= $this->url('/import/trello') ?>"><?= $this->icon('left') ?> <span><?= $this->te('Import a Trello board') ?></span></a>
    <h1><?= $this->te('Check the import') ?></h1>
    <p class="muted"><span dir="auto"><?= $this->e($import['fileName']) ?></span></p>

    <?php if ($preview['importedAt'] !== null): ?>
        <div class="alert" role="alert"><?= $this->icon('flag') ?><?= $this->te('This Trello board was already imported here on :date. Importing it again makes a second board.', ['date' => substr($preview['importedAt'], 0, 10)]) ?></div>
    <?php endif; ?>

    <section class="card stack" aria-labelledby="counts-title">
        <h2 id="counts-title" class="section-title"><?= $this->icon('board') ?> <span dir="auto"><?= $this->e($preview['name']) ?></span></h2>
        <dl class="import-counts">
            <?php foreach ($counts as $label => $value): ?>
                <div><dt><?= $this->te($label) ?></dt><dd><?= (int) $value ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <?php if ($preview['skippedLists'] + $preview['skippedCards'] > 0): ?>
            <p class="small muted"><?= $this->te('Left out because they are archived in Trello: :lists lists and :cards cards.', ['lists' => (string) $preview['skippedLists'], 'cards' => (string) $preview['skippedCards']]) ?></p>
        <?php endif; ?>
        <?php if ($preview['uploads'] > 0): ?>
            <p class="small muted"><?= $this->te(':count of the attachments are files stored on Trello; they come in as links to Trello.', ['count' => (string) $preview['uploads']]) ?></p>
        <?php endif; ?>
        <?php if ($preview['matched'] !== []): ?>
            <p class="small"><?= $this->te('Found here and added to the board:') ?> <span dir="auto"><?= $this->e(implode('، ', $preview['matched'])) ?></span></p>
        <?php endif; ?>
        <?php if ($preview['unmatched'] !== []): ?>
            <p class="small muted"><?= $this->te('Not found in this workspace (cards keep no member for them):') ?> <span dir="auto"><?= $this->e(implode('، ', $preview['unmatched'])) ?></span></p>
        <?php endif; ?>
    </section>

    <section class="card stack" aria-labelledby="sample-title">
        <h2 id="sample-title" class="section-title"><?= $this->icon('eye') ?> <?= $this->te('First cards of each list') ?></h2>
        <div class="import-lists">
            <?php foreach ($preview['sample'] as $list): ?>
                <div>
                    <strong dir="auto"><?= $this->e($list['name']) ?></strong> <span class="muted small">(<?= (int) $list['total'] ?>)</span>
                    <ul class="small">
                        <?php foreach ($list['cards'] as $card): ?>
                            <li dir="auto"><?= $this->e($card['title']) ?><?= $card['due'] !== null ? ' · <span dir="ltr">' . $this->e($card['due']) . '</span>' : '' ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <form action="<?= $action ?>" method="post" class="card stack" novalidate>
        <?= $this->csrfField() ?>
        <label>
            <span><?= $this->te('Board name') ?></span>
            <input name="name" maxlength="100" dir="auto" value="<?= $this->e($form->old('name', $preview['name'])) ?>">
        </label>
        <label>
            <span><?= $this->te('Visibility') ?></span>
            <select name="visibility">
                <option value="private"><?= $this->te('Private: only board members') ?></option>
                <option value="workspace"><?= $this->te('Workspace: every member can view') ?></option>
            </select>
        </label>
        <?php if ($preview['importedAt'] !== null): ?>
            <label class="check"><input type="checkbox" name="again" value="1" <?= $form->error('again') ? 'aria-invalid="true" aria-describedby="again-error"' : '' ?>> <?= $this->te('Import it again as another board') ?></label>
            <?= $this->partial('partials/field-error', ['field' => 'again']) ?>
        <?php endif; ?>
        <?= $this->partial('partials/field-error', ['field' => 'import']) ?>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= $this->icon('check') ?><?= $this->te('Create the board') ?></button>
        </div>
    </form>
    <form action="<?= $action ?>/cancel" method="post">
        <?= $this->csrfField() ?>
        <button class="btn btn-ghost" type="submit"><?= $this->icon('close') ?><?= $this->te('Cancel the import') ?></button>
    </form>
</main>
