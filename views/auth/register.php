<?php
/** @var \App\View\View $this @var string $appName */
$form = $this->form;
$fields = [
    ['name', 'Name', 'text', 'name'],
    ['email', 'Email', 'email', 'email'],
    ['password', 'Password', 'password', 'new-password'],
    ['password_confirmation', 'Confirm password', 'password', 'new-password'],
];
?>
<main class="auth">
    <section class="auth-art" aria-hidden="true">
        <div class="floating-card fc-1"><span class="dot dot-mint"></span><?= $this->te('Plan the sprint') ?></div>
        <div class="floating-card fc-2"><span class="dot dot-coral"></span><?= $this->te('Review the design') ?></div>
        <div class="floating-card fc-3"><?= $this->icon('check') ?><?= $this->te('Ship the release') ?></div>
        <h2><?= $this->te('Get your team\'s work') ?><br><span class="gradient-text"><?= $this->te('out of your head.') ?></span></h2>
        <p><?= $this->te('Free, private and fast. Your data stays in your own database.') ?></p>
    </section>

    <section class="card auth-card">
        <a class="brand" href="<?= $this->url('/login') ?>">
            <span class="brand-mark"><?= $this->icon('check') ?></span><span><?= $this->e($appName) ?></span>
        </a>
        <h1><?= $this->te('Create your account') ?></h1>
        <p class="muted"><?= $this->te('It takes less than a minute.') ?></p>

        <form action="<?= $this->url('/register') ?>" method="post" class="stack" novalidate>
            <?= $this->csrfField() ?>
            <?php foreach ($fields as [$field, $label, $type, $autocomplete]): ?>
                <label>
                    <span><?= $this->te($label) ?></span>
                    <input type="<?= $type ?>" name="<?= $field ?>" required autocomplete="<?= $autocomplete ?>"
                           <?= $type !== 'password' ? 'value="' . $this->e($form->old($field)) . '"' : '' ?>
                           <?= $field === 'name' ? 'dir="auto" autofocus' : '' ?>
                           aria-invalid="<?= $form->error($field) ? 'true' : 'false' ?>">
                    <?= $this->partial('partials/field-error', ['field' => $field]) ?>
                </label>
            <?php endforeach; ?>
            <button class="btn btn-primary btn-block" type="submit"><?= $this->te('Create account') ?></button>
        </form>

        <p class="muted small center"><?= $this->te('Already have an account?') ?> <a href="<?= $this->url('/login') ?>"><?= $this->te('Log in') ?></a></p>
    </section>
</main>
