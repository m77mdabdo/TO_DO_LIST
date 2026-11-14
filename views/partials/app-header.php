<?php
/**
 * The header on every signed-in page. Its data comes from a view composer
 * registered by the Workspace module.
 *
 * @var \App\View\View $this
 * @var string $appName
 * @var \App\Modules\Identity\Contract\User $user
 * @var list<\App\Modules\Workspace\Contract\WorkspaceSummary> $workspaces
 * @var ?\App\Modules\Workspace\Contract\WorkspaceSummary $currentWorkspace
 * @var ?string $searchQuery
 */
$workspaces ??= [];
$currentWorkspace ??= null;
?>
<header class="topbar">
    <div class="topbar-start">
        <a class="brand" href="<?= $this->url('/') ?>">
            <span class="brand-mark"><?= $this->icon('check') ?></span>
            <span class="hide-sm"><?= $this->e($appName) ?></span>
        </a>

        <?php if ($currentWorkspace): ?>
            <details class="menu ws-switcher">
                <summary class="btn btn-ghost">
                    <span class="ws-badge"><?= $this->e($currentWorkspace->initial()) ?></span>
                    <span class="ws-name" dir="auto"><?= $this->e($currentWorkspace->name) ?></span>
                    <?= $this->icon('chevron') ?>
                </summary>
                <div class="menu-panel">
                    <p class="menu-title"><?= $this->te('Your workspaces') ?></p>
                    <?php foreach ($workspaces as $workspace): ?>
                        <form action="<?= $this->url('/workspaces/' . $workspace->publicId . '/switch') ?>" method="post">
                            <?= $this->csrfField() ?>
                            <button class="menu-item <?= $workspace->id === $currentWorkspace->id ? 'is-current' : '' ?>" type="submit">
                                <span class="ws-badge"><?= $this->e($workspace->initial()) ?></span>
                                <span dir="auto"><?= $this->e($workspace->name) ?></span>
                                <small class="muted"><?= $this->te($workspace->role->label()) ?></small>
                            </button>
                        </form>
                    <?php endforeach; ?>
                    <hr>
                    <a class="menu-item" href="<?= $this->url('/workspaces/' . $currentWorkspace->publicId . '/members') ?>"><?= $this->icon('users') ?><?= $this->te('Members') ?></a>
                    <a class="menu-item" href="<?= $this->url('/workspaces/' . $currentWorkspace->publicId . '/cards') ?>"><?= $this->icon('table') ?><?= $this->te('All cards') ?></a>
                    <a class="menu-item" href="<?= $this->url('/workspaces/' . $currentWorkspace->publicId . '/report') ?>"><?= $this->icon('chart') ?><?= $this->te('Team report') ?></a>
                    <a class="menu-item" href="<?= $this->url('/workspaces/new') ?>"><?= $this->icon('plus') ?><?= $this->te('New company') ?></a>
                </div>
            </details>
        <?php endif; ?>
    </div>

    <?php if (isset($user)): ?>
        <form action="<?= $this->url('/search') ?>" method="get" class="topbar-search" role="search">
            <label class="visually-hidden" for="topbar-search"><?= $this->te('Search') ?></label>
            <?= $this->icon('search') ?>
            <input type="search" id="topbar-search" name="q" value="<?= $this->e($searchQuery ?? '') ?>" maxlength="100" dir="auto"
                   class="js-global-search" placeholder="<?= $this->te('Search') ?>">
            <kbd class="hide-sm">/</kbd>
        </form>
    <?php endif; ?>

    <div class="topbar-actions">
        <?php if (isset($user)): ?>
            <button class="icon-btn hide-sm js-shortcuts" type="button" title="<?= $this->te('Keyboard shortcuts') ?> (?)" aria-label="<?= $this->te('Keyboard shortcuts') ?>"><?= $this->icon('keyboard') ?></button>
            <a class="icon-btn" href="<?= $this->url('/my-tasks') ?>" title="<?= $this->te('My tasks') ?>" aria-label="<?= $this->te('My tasks') ?>"><?= $this->icon('inbox') ?></a>
            <?= $this->slot('header.actions', ['user' => $user]) ?>
        <?php endif; ?>
        <button class="icon-btn js-theme" type="button" title="<?= $this->te('Toggle dark mode') ?>">
            <span class="show-light"><?= $this->icon('moon') ?></span><span class="show-dark"><?= $this->icon('sun') ?></span>
        </button>
        <?php if (isset($user)): ?>
            <span class="avatar" title="<?= $this->e($user->email) ?>"><?= $this->e($user->initial()) ?></span>
            <form action="<?= $this->url('/logout') ?>" method="post">
                <?= $this->csrfField() ?>
                <button class="icon-btn" title="<?= $this->te('Log out') ?>"><?= $this->icon('logout') ?></button>
            </form>
        <?php endif; ?>
    </div>
</header>
