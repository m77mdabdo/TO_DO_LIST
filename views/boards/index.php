<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var list<\App\Modules\Board\Contract\BoardSummary> $boards
 * @var list<\App\Modules\Board\Contract\BoardSummary> $archived
 * @var \App\Modules\Identity\Contract\User $user
 * @var string $greeting
 */
use App\Modules\Board\Contract\BoardSummary;

$form = $this->form;
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap">
    <section class="home-head">
        <div>
            <p class="eyebrow" dir="auto"><?= $this->e($workspace->companyName) ?></p>
            <h1><?= $this->te($greeting) ?><?= $this->te(',') ?> <span class="gradient-text" dir="auto"><?= $this->e($user->firstName()) ?></span></h1>
            <p class="muted"><?= $this->te('Pick a board or start a new one.') ?></p>
        </div>
    </section>

    <section class="board-grid" aria-label="<?= $this->te('Boards') ?>">
        <?php foreach ($boards as $board): ?>
            <a class="board-tile tile-<?= $this->e($board->color) ?>" href="<?= $this->url('/boards/' . $board->publicId) ?>">
                <strong dir="auto"><?= $this->e($board->name) ?></strong>
                <span class="tile-meta">
                    <?= $this->icon($board->visibility->value === 'private' ? 'lock' : 'users') ?>
                    <?= $this->te($board->visibility->value === 'private' ? 'Private' : 'Workspace') ?>
                </span>
            </a>
        <?php endforeach; ?>

        <?php if ($workspace->role->canCreateBoards()): ?>
            <details class="board-tile tile-new" <?= $form->error('name') ? 'open' : '' ?>>
                <summary><?= $this->icon('plus') ?> <?= $this->te('Create board') ?></summary>
                <form action="<?= $this->url('/boards') ?>" method="post" class="stack" novalidate>
                    <?= $this->csrfField() ?>
                    <label>
                        <span><?= $this->te('Board name') ?></span>
                        <input name="name" required maxlength="100" dir="auto" value="<?= $this->e($form->old('name')) ?>"
                               aria-invalid="<?= $form->error('name') ? 'true' : 'false' ?>">
                        <?= $this->partial('partials/field-error', ['field' => 'name']) ?>
                    </label>
                    <fieldset class="swatches">
                        <legend><?= $this->te('Color') ?></legend>
                        <?php foreach (BoardSummary::COLORS as $i => $color): ?>
                            <label class="swatch tile-<?= $color ?>">
                                <input type="radio" name="color" value="<?= $color ?>" <?= $i === 0 ? 'checked' : '' ?>>
                                <span class="visually-hidden"><?= $this->e($color) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <label>
                        <span><?= $this->te('Visibility') ?></span>
                        <select name="visibility">
                            <option value="private"><?= $this->te('Private: only board members') ?></option>
                            <option value="workspace"><?= $this->te('Workspace: every member can view') ?></option>
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit"><?= $this->te('Create board') ?></button>
                </form>
            </details>
        <?php endif; ?>
    </section>

    <?php if (!$boards && !$workspace->role->canCreateBoards()): ?>
        <p class="muted center"><?= $this->te('No boards have been shared with you yet.') ?></p>
    <?php endif; ?>

    <?php if ($archived): ?>
        <details class="archived-boards">
            <summary><?= $this->icon('archive') ?> <?= $this->te('Archived boards') ?> (<?= count($archived) ?>)</summary>
            <ul>
                <?php foreach ($archived as $board): ?>
                    <li>
                        <span dir="auto"><?= $this->e($board->name) ?></span>
                        <a class="btn btn-ghost" href="<?= $this->url('/boards/' . $board->publicId) ?>"><?= $this->te('Open') ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>
</main>
