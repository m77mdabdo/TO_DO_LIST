<?php

require_once __DIR__ . '/App.php';

use Classes\Csrf;

if ($auth->check()) {
    $request->redirect('index.php');
}

$inlineErrors = true;
$fieldErrors  = errors();

$pageTitle = 'Log in';
$bodyClass = 'page-auth';
require __DIR__ . '/inc/header.php';
?>

<main class="auth">
    <section class="auth-art" aria-hidden="true">
        <div class="floating-card fc-1"><span class="dot dot-violet"></span>Plan the week</div>
        <div class="floating-card fc-2"><span class="dot dot-amber"></span>Gym at 7pm</div>
        <div class="floating-card fc-3"><?= icon('check') ?>Finish the report</div>
        <h2>Small steps,<br><span class="gradient-text">every single day.</span></h2>
        <p>Your tasks, lists and deadlines in one bright, simple board.</p>
    </section>

    <section class="card auth-card">
        <a class="brand" href="<?= url('login.php') ?>">
            <span class="brand-mark"><?= icon('check') ?></span><span><?= e($config['app_name']) ?></span>
        </a>
        <h1>Welcome back</h1>
        <p class="muted">Log in to pick up where you left off.</p>

        <?php if (isset($fieldErrors['login'])): ?>
            <div class="alert"><?= icon('flag') ?><?= e($fieldErrors['login']) ?></div>
        <?php endif; ?>

        <form action="<?= url('handle/login.php') ?>" method="post" class="stack" novalidate>
            <?= Csrf::field() ?>
            <label>
                <span>Email</span>
                <input type="email" name="email" required autocomplete="email" autofocus value="<?= e(old('email')) ?>"
                       aria-invalid="<?= isset($fieldErrors['email']) ? 'true' : 'false' ?>">
                <?php if (isset($fieldErrors['email'])): ?><small class="field-error"><?= e($fieldErrors['email']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" required autocomplete="current-password"
                       aria-invalid="<?= isset($fieldErrors['password']) ? 'true' : 'false' ?>">
                <?php if (isset($fieldErrors['password'])): ?><small class="field-error"><?= e($fieldErrors['password']) ?></small><?php endif; ?>
            </label>
            <button class="btn btn-primary btn-block" type="submit">Log in</button>
        </form>

        <p class="muted small center">New here? <a href="<?= url('register.php') ?>">Create an account</a></p>
    </section>
</main>

<?php require __DIR__ . '/inc/footer.php'; ?>
