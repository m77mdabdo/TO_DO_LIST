<?php /** @var \App\View\View $this @var string $appName */ $form = $this->form; ?>
<main class="auth auth-single">
    <section class="card auth-card">
        <a class="brand" href="<?= $this->url('/login') ?>">
            <span class="brand-mark"><?= $this->icon('check') ?></span><span><?= $this->e($appName) ?></span>
        </a>
        <h1><?= $this->te('Forgot your password?') ?></h1>
        <p class="muted"><?= $this->te('Enter the email of your account and we will send you a link to choose a new password.') ?></p>

        <form action="<?= $this->url('/forgot-password') ?>" method="post" class="stack" novalidate>
            <?= $this->csrfField() ?>
            <label>
                <span><?= $this->te('Email') ?></span>
                <input type="email" name="email" required autocomplete="email" autofocus maxlength="190" value="<?= $this->e($form->old('email')) ?>"
                       aria-invalid="<?= $form->error('email') ? 'true' : 'false' ?>" aria-describedby="email-error">
                <?= $this->partial('partials/field-error', ['field' => 'email']) ?>
            </label>
            <button class="btn btn-primary btn-block" type="submit"><?= $this->te('Send the link') ?></button>
        </form>

        <p class="muted small center"><a href="<?= $this->url('/login') ?>"><?= $this->te('Back to log in') ?></a></p>
    </section>
</main>
