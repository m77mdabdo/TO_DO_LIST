<?php /** @var \App\View\View $this @var string $appName */ $form = $this->form; ?>
<main class="auth">
    <section class="card auth-card stack">
        <a class="brand" href="<?= $this->url('/login') ?>">
            <span class="brand-mark"><?= $this->icon('check') ?></span><span><?= $this->e($appName) ?></span>
        </a>
        <h1><?= $this->te('Two-step sign-in') ?></h1>
        <p class="muted"><?= $this->te('Open your authenticator app and type the 6-digit code for this account. Lost your phone? Type one of your recovery codes instead.') ?></p>

        <form action="<?= $this->url('/login/two-factor') ?>" method="post" class="stack" novalidate>
            <?= $this->csrfField() ?>
            <label>
                <span><?= $this->te('Code') ?></span>
                <input type="text" name="code" required autocomplete="one-time-code" inputmode="text" autofocus maxlength="20" dir="ltr"
                       aria-invalid="<?= $form->error('code') ? 'true' : 'false' ?>" <?= $form->error('code') ? 'aria-describedby="code-error"' : '' ?>>
                <?= $this->partial('partials/field-error', ['field' => 'code']) ?>
            </label>
            <button class="btn btn-primary btn-block" type="submit"><?= $this->te('Sign in') ?></button>
        </form>
        <form action="<?= $this->url('/login/two-factor') ?>" method="post" class="center">
            <?= $this->csrfField() ?>
            <input type="hidden" name="cancel" value="1">
            <button class="link-btn" type="submit"><?= $this->te('Use another account') ?></button>
        </form>
    </section>
</main>
