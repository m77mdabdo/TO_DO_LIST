<?php

require_once __DIR__ . '/App.php';

use Classes\Csrf;

if ($auth->check()) {
    $request->redirect('index.php');
}

$inlineErrors = true;
$fieldErrors  = errors();

$pageTitle = 'Create account';
$bodyClass = 'page-auth';
require __DIR__ . '/inc/header.php';
?>

<main class="auth">
    <section class="auth-art" aria-hidden="true">
        <div class="floating-card fc-1"><span class="dot dot-mint"></span>Read 20 pages</div>
        <div class="floating-card fc-2"><span class="dot dot-coral"></span>Pay the bills</div>
        <div class="floating-card fc-3"><?= icon('check') ?>Call mom</div>
        <h2>Get your day<br><span class="gradient-text">out of your head.</span></h2>
        <p>Free, private and fast. Your tasks stay in your own database.</p>
    </section>

    <section class="card auth-card">
        <a class="brand" href="<?= url('login.php') ?>">
            <span class="brand-mark"><?= icon('check') ?></span><span><?= e($config['app_name']) ?></span>
        </a>
        <h1>Create your account</h1>
        <p class="muted">It takes less than a minute.</p>

        <form action="<?= url('handle/register.php') ?>" method="post" class="stack" novalidate>
            <?= Csrf::field() ?>
            <?php foreach ([
                ['name', 'Name', 'text', 'name'],
                ['email', 'Email', 'email', 'email'],
                ['password', 'Password', 'password', 'new-password'],
                ['password_confirmation', 'Confirm password', 'password', 'new-password'],
            ] as [$field, $label, $type, $autocomplete]): ?>
                <label>
                    <span><?= $label ?></span>
                    <input type="<?= $type ?>" name="<?= $field ?>" required autocomplete="<?= $autocomplete ?>"
                           <?= $type !== 'password' ? 'value="' . e(old($field)) . '"' : '' ?>
                           <?= $field === 'name' ? 'dir="auto" autofocus' : '' ?>
                           aria-invalid="<?= isset($fieldErrors[$field]) ? 'true' : 'false' ?>">
                    <?php if (isset($fieldErrors[$field])): ?><small class="field-error"><?= e($fieldErrors[$field]) ?></small><?php endif; ?>
                </label>
            <?php endforeach; ?>
            <button class="btn btn-primary btn-block" type="submit">Create account</button>
        </form>

        <p class="muted small center">Already have an account? <a href="<?= url('login.php') ?>">Log in</a></p>
    </section>
</main>

<?php require __DIR__ . '/inc/footer.php'; ?>
