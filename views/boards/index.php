<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var list<\App\Modules\Board\Contract\BoardSummary> $boards
 * @var list<\App\Modules\Board\Contract\BoardSummary> $archived
 * @var \App\Modules\Identity\Contract\User $user
 * @var string $greeting
 * @var list<\App\Modules\Board\Contract\BoardSummary> $templates boards saved as templates
 * @var array<string, array{name: string, description: string, lists: list<string>}> $builtIns
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
                <?php if ($board->isTemplate): ?><span class="tile-badge"><?= $this->icon('copy') ?><?= $this->te('Template') ?></span><?php endif; ?>
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
                        <span><?= $this->te('Start from') ?></span>
                        <select name="template">
                            <option value=""><?= $this->te('A blank board') ?></option>
                            <optgroup label="<?= $this->te('Ready-made templates') ?>">
                                <?php foreach ($builtIns as $key => $template): ?>
                                    <option value="builtin:<?= $this->e($key) ?>" <?= $form->old('template') === 'builtin:' . $key ? 'selected' : '' ?>><?= $this->te($template['name']) ?>: <?= $this->e(implode(' · ', array_map(fn ($l) => $this->t($l), $template['lists']))) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php if ($templates): ?>
                                <optgroup label="<?= $this->te('Your templates') ?>">
                                    <?php foreach ($templates as $template): ?>
                                        <option value="<?= $this->e($template->publicId) ?>" <?= $form->old('template') === $template->publicId ? 'selected' : '' ?>><?= $this->e($template->name) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </label>
                    <?php if ($templates): ?>
                        <label class="check"><input type="checkbox" name="with_cards" value="1"> <?= $this->te('Copy the cards of a saved template too') ?></label>
                    <?php endif; ?>
                    <label>
                        <span><?= $this->te('Board name') ?></span>
                        <input name="name" maxlength="100" dir="auto" placeholder="<?= $this->te('Leave empty to use the template name') ?>" value="<?= $this->e($form->old('name')) ?>"
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
