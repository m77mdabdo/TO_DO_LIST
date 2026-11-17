<?php
/**
 * The person's own settings: name and language, notification choices (slot),
 * downloading their data, and deleting the account.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Identity\Contract\User $user
 * @var ?string $locale the saved language, null = the app default
 * @var list<string> $locales
 * @var list<\App\Modules\Workspace\Contract\WorkspaceSummary> $blockers workspaces to hand over before deleting
 */
$form = $this->form;
$names = ['ar' => 'العربية', 'en' => 'English'];
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow stack">
    <h1><?= $this->te('Your account') ?></h1>
    <nav class="row-actions" aria-label="<?= $this->te('Account pages') ?>">
        <a class="btn btn-ghost" href="<?= $this->url('/account/security') ?>"><?= $this->icon('shield') ?><?= $this->te('Account security') ?></a>
    </nav>

    <section class="card stack" id="profile" aria-labelledby="profile-title">
        <h2 id="profile-title"><?= $this->te('Profile') ?></h2>
        <form action="<?= $this->url('/account/profile') ?>" method="post" class="stack" novalidate>
            <?= $this->csrfField() ?>
            <label><span><?= $this->te('Name') ?></span>
                <input name="name" required minlength="2" maxlength="60" dir="auto" autocomplete="name" value="<?= $this->e($form->old('name') ?: $user->name) ?>"
                       aria-invalid="<?= $form->error('name') ? 'true' : 'false' ?>" <?= $form->error('name') ? 'aria-describedby="name-error"' : '' ?>>
                <?= $this->partial('partials/field-error', ['field' => 'name']) ?>
            </label>
            <label><span><?= $this->te('Language') ?></span>
                <select name="locale">
                    <option value=""><?= $this->te('The app default') ?></option>
                    <?php foreach ($locales as $code): ?>
                        <option value="<?= $this->e($code) ?>" lang="<?= $this->e($code) ?>" <?= $locale === $code ? 'selected' : '' ?>><?= $this->e($names[$code] ?? $code) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $this->partial('partials/field-error', ['field' => 'locale']) ?>
            </label>
            <p class="muted small"><?= $this->te('Your language is used every time you sign in, on any device.') ?></p>
            <p class="muted small"><?= $this->te('Email') ?>: <bdi><?= $this->e($user->email) ?></bdi></p>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button></div>
        </form>
    </section>

    <?= $this->slot('account.settings', ['user' => $user]) ?>

    <section class="card stack" id="data" aria-labelledby="data-title">
        <h2 id="data-title"><?= $this->te('Your data') ?></h2>
        <p><?= $this->te('Download everything the app keeps about you: your profile, workspaces, comments you wrote, cards assigned to you, notifications, settings and security events. Board contents are exported from each board.') ?></p>
        <div><a class="btn btn-ghost" href="<?= $this->url('/account/export.json') ?>"><?= $this->icon('download') ?><?= $this->te('Download my data (JSON)') ?></a></div>
    </section>

    <section class="card stack" id="delete" aria-labelledby="delete-title">
        <h2 id="delete-title"><?= $this->te('Delete your account') ?></h2>
        <p><?= $this->te('Your name, email, settings, memberships and notifications are removed and you are signed out everywhere. Cards and comments you wrote stay on their boards, shown as by “Deleted user”. This can\'t be undone.') ?></p>
        <?= $this->partial('partials/field-error', ['field' => 'delete']) ?>
        <?php if ($blockers !== []): ?>
            <div class="alert" role="note">
                <p><?= $this->te('First hand over the workspaces you own alone:') ?></p>
                <ul class="plain-list">
                    <?php foreach ($blockers as $workspace): ?>
                        <li><a href="<?= $this->url('/workspaces/' . $workspace->publicId . '/members#ownership') ?>"><bdi><?= $this->e($workspace->companyName) ?></bdi></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <form action="<?= $this->url('/account/delete') ?>" method="post" class="stack" novalidate
                  data-confirm="<?= $this->te('Delete your account for good?') ?>">
                <?= $this->csrfField() ?>
                <label><span><?= $this->te('Your password') ?></span>
                    <input type="password" name="password" required autocomplete="current-password"
                           aria-invalid="<?= $form->error('password') ? 'true' : 'false' ?>" <?= $form->error('password') ? 'aria-describedby="password-error"' : '' ?>>
                    <?= $this->partial('partials/field-error', ['field' => 'password']) ?>
                </label>
                <label><span><?= $this->te('Type DELETE to confirm') ?></span>
                    <input name="confirm" required autocomplete="off" dir="ltr"
                           aria-invalid="<?= $form->error('confirm') ? 'true' : 'false' ?>" <?= $form->error('confirm') ? 'aria-describedby="confirm-error"' : '' ?>>
                    <?= $this->partial('partials/field-error', ['field' => 'confirm']) ?>
                </label>
                <div class="form-actions"><button class="btn btn-danger-ghost" type="submit"><?= $this->icon('trash') ?><?= $this->te('Delete my account') ?></button></div>
            </form>
        <?php endif; ?>
    </section>
</main>
