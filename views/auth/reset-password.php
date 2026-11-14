<?php /** @var \App\View\View $this @var string $appName @var string $token */ $form = $this->form; ?>
<main class="auth auth-single">
    <section class="card auth-card">
        <a class="brand" href="<?= $this->url('/login') ?>">
            <span class="brand-mark"><?= $this->icon('check') ?></span><span><?= $this->e($appName) ?></span>
        </a>
        <h1><?= $this->te('Choose a new password') ?></h1>
        <p class="muted"><?= $this->te('At least 8 characters. Your other devices will be signed out.') ?></p>

        <form action="<?= $this->url('/reset-password/' . $token) ?>" method="post" class="stack" novalidate>
            <?= $this->csrfField() ?>
            <label>
                <span><?= $this->te('New password') ?></span>
                <input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password" autofocus
                       aria-invalid="<?= $form->error('password') ? 'true' : 'false' ?>">
                <?= $this->partial('partials/field-error', ['field' => 'password']) ?>
            </label>
            <label>
                <span><?= $this->te('Confirm password') ?></span>
                <input type="password" name="password_confirmation" required maxlength="72" autocomplete="new-password"
                       aria-invalid="<?= $form->error('password_confirmation') ? 'true' : 'false' ?>">
                <?= $this->partial('partials/field-error', ['field' => 'password_confirmation']) ?>
            </label>
            <button class="btn btn-primary btn-block" type="submit"><?= $this->te('Save the new password') ?></button>
        </form>
    </section>
</main>
