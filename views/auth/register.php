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
        <div class="floating-card fc-1"><span class="dot dot-mint"></span>Read 20 pages</div>
        <div class="floating-card fc-2"><span class="dot dot-coral"></span>Pay the bills</div>
        <div class="floating-card fc-3"><?= $this->icon('check') ?>Call mom</div>
        <h2>Get your day<br><span class="gradient-text">out of your head.</span></h2>
        <p>Free, private and fast. Your tasks stay in your own database.</p>
    </section>

    <section class="card auth-card">
        <a class="brand" href="<?= $this->url('/login') ?>">
            <span class="brand-mark"><?= $this->icon('check') ?></span><span><?= $this->e($appName) ?></span>
        </a>
        <h1>Create your account</h1>
        <p class="muted">It takes less than a minute.</p>

        <form action="<?= $this->url('/register') ?>" method="post" class="stack" novalidate>
            <?= $this->csrfField() ?>
            <?php foreach ($fields as [$field, $label, $type, $autocomplete]): ?>
                <label>
                    <span><?= $label ?></span>
                    <input type="<?= $type ?>" name="<?= $field ?>" required autocomplete="<?= $autocomplete ?>"
                           <?= $type !== 'password' ? 'value="' . $this->e($form->old($field)) . '"' : '' ?>
                           <?= $field === 'name' ? 'dir="auto" autofocus' : '' ?>
                           aria-invalid="<?= $form->error($field) ? 'true' : 'false' ?>">
                    <?= $this->partial('partials/field-error', ['field' => $field]) ?>
                </label>
            <?php endforeach; ?>
            <button class="btn btn-primary btn-block" type="submit">Create account</button>
        </form>

        <p class="muted small center">Already have an account? <a href="<?= $this->url('/login') ?>">Log in</a></p>
    </section>
</main>
