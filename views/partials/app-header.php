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
                    <a class="menu-item" href="<?= $this->url('/workspaces/new') ?>"><?= $this->icon('plus') ?><?= $this->te('New company') ?></a>
                </div>
            </details>
        <?php endif; ?>
    </div>

    <div class="topbar-actions">
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
