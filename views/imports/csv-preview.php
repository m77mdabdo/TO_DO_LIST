<?php
/**
 * Step 2 of a CSV import: which column is what, and what happens to every row.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var array<string, mixed> $import
 * @var list<string> $header
 * @var array<string, int> $map
 * @var array{create_lists: bool, skip_duplicates: bool} $options
 * @var array<string, string> $fields
 * @var ?string $runError
 * @var array{rows: list<array<string, mixed>>, ok: int, skipped: int, errors: int, newLists: list<string>, newLabels: list<string>, ignoredLabels: list<string>} $plan
 */
$action = $this->url('/imports/' . $import['publicId']);
$statusText = ['ok' => 'Will be added', 'skip' => 'Skipped', 'error' => 'Has a problem'];
$shown = array_slice($plan['rows'], 0, 300);
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap stack import-page">
    <a class="back-link" href="<?= $this->url('/boards/' . $board->publicId) ?>"><?= $this->icon('left') ?> <span dir="auto"><?= $this->e($board->name) ?></span></a>
    <h1><?= $this->te('Check the import') ?></h1>
    <p class="muted"><span dir="auto"><?= $this->e($import['fileName']) ?></span> · <?= $this->te(':count rows', ['count' => (string) count($plan['rows'])]) ?></p>

    <?php if ($runError !== null): ?>
        <div class="alert" role="alert"><?= $this->icon('flag') ?><?= $this->te($runError) ?></div>
    <?php endif; ?>

    <form action="<?= $action ?>" method="post" class="stack">
        <?= $this->csrfField() ?>
        <section class="card stack" aria-labelledby="map-title">
            <h2 id="map-title" class="section-title"><?= $this->icon('table') ?> <?= $this->te('Columns') ?></h2>
            <div class="import-map">
                <?php foreach ($fields as $field => $label): ?>
                    <label>
                        <span><?= $this->te($label) ?></span>
                        <select name="map[<?= $field ?>]">
                            <option value="-1"><?= $this->te('Not used') ?></option>
                            <?php foreach ($header as $i => $name): ?>
                                <option value="<?= $i ?>" <?= $map[$field] === $i ? 'selected' : '' ?> dir="auto"><?= $this->e($name !== '' ? $name : $this->t('Column :number', ['number' => (string) ($i + 1)])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endforeach; ?>
            </div>
            <label class="check"><input type="checkbox" name="create_lists" value="1" <?= $options['create_lists'] ? 'checked' : '' ?>> <?= $this->te('Create lists that are not on the board yet') ?></label>
            <label class="check"><input type="checkbox" name="skip_duplicates" value="1" <?= $options['skip_duplicates'] ? 'checked' : '' ?>> <?= $this->te('Skip rows whose title is already a card in the same list') ?></label>
            <div>
                <button class="btn btn-ghost" type="submit" name="action" value="preview"><?= $this->icon('eye') ?><?= $this->te('Update the preview') ?></button>
            </div>
        </section>

        <section class="card stack" aria-labelledby="result-title">
            <h2 id="result-title" class="section-title"><?= $this->icon('checklist') ?> <?= $this->te('What will happen') ?></h2>
            <ul class="import-summary" role="list">
                <li class="run-ok"><strong><?= $plan['ok'] ?></strong> <?= $this->te('cards will be added') ?></li>
                <li><strong><?= $plan['skipped'] ?></strong> <?= $this->te('rows skipped as duplicates') ?></li>
                <li class="<?= $plan['errors'] > 0 ? 'run-failed' : '' ?>"><strong><?= $plan['errors'] ?></strong> <?= $this->te('rows have a problem and will be left out') ?></li>
            </ul>
            <?php if ($plan['newLists'] !== []): ?>
                <p class="small"><?= $this->te('New lists:') ?> <span dir="auto"><?= $this->e(implode('، ', $plan['newLists'])) ?></span></p>
            <?php endif; ?>
            <?php if ($plan['newLabels'] !== []): ?>
                <p class="small"><?= $this->te('New labels:') ?> <span dir="auto"><?= $this->e(implode('، ', $plan['newLabels'])) ?></span></p>
            <?php endif; ?>
            <?php if ($plan['ignoredLabels'] !== []): ?>
                <p class="small muted"><?= $this->te('Labels left out (only board admins can add labels):') ?> <span dir="auto"><?= $this->e(implode('، ', $plan['ignoredLabels'])) ?></span></p>
            <?php endif; ?>

            <div class="table-wrap" tabindex="0" role="region" aria-labelledby="result-title">
                <table class="data-table import-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= $this->te('Row') ?></th>
                            <th scope="col"><?= $this->te('Result') ?></th>
                            <th scope="col"><?= $this->te('Title') ?></th>
                            <th scope="col"><?= $this->te('List') ?></th>
                            <th scope="col"><?= $this->te('Dates') ?></th>
                            <th scope="col"><?= $this->te('Notes') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shown as $row): ?>
                            <tr class="import-<?= $row['status'] ?>">
                                <td><?= (int) $row['line'] ?></td>
                                <td><span class="pill run-<?= $row['status'] === 'ok' ? 'ok' : ($row['status'] === 'error' ? 'failed' : 'pending') ?>"><?= $this->te($statusText[$row['status']]) ?></span></td>
                                <td dir="auto"><?= $this->e($row['title']) ?><?= $row['complete'] ? ' ✓' : '' ?></td>
                                <td dir="auto"><?= $this->e($row['list']) ?></td>
                                <td dir="ltr" class="small"><?= $this->e(trim(($row['start'] ?? '') . ' → ' . ($row['due'] ?? ''), ' →')) ?></td>
                                <td class="small">
                                    <?php foreach ([...$row['problems'], ...$row['notes']] as [$message, $replace]): ?>
                                        <div dir="auto"><?= $this->te($message, $replace) ?></div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($plan['rows']) > count($shown)): ?>
                <p class="muted small"><?= $this->te('Only the first 300 rows are shown; the counts above cover every row.') ?></p>
            <?php endif; ?>
        </section>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit" name="action" value="run" <?= $plan['ok'] === 0 ? 'disabled' : '' ?>>
                <?= $this->icon('check') ?><?= $this->te('Import :count cards', ['count' => (string) $plan['ok']]) ?>
            </button>
        </div>
    </form>
    <form action="<?= $action ?>/cancel" method="post">
        <?= $this->csrfField() ?>
        <button class="btn btn-ghost" type="submit"><?= $this->icon('close') ?><?= $this->te('Cancel the import') ?></button>
    </form>
</main>
