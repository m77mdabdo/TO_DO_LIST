<?php
/**
 * @var App\View\ViewHelpers $v
 * @var App\View\View $view
 * @var string $appName
 */
?>
<main class="auth">
    <?= $view->partial('partials/auth_art', [
        'cards'     => [['violet', 'Plan the week'], ['amber', 'Gym at 7pm'], 'Finish the report'],
        'headline'  => 'Small steps,',
        'highlight' => 'every single day.',
        'text'      => 'Your tasks, lists and deadlines in one bright, simple board.',
    ]) ?>

    <section class="card auth-card">
        <a class="brand" href="<?= $v->url('/login') ?>">
            <span class="brand-mark"><?= $v->icon('check') ?></span><span><?= $v->e($appName) ?></span>
        </a>
        <h1>Welcome back</h1>
        <p class="muted">Log in to pick up where you left off.</p>

        <?php if ($message = $v->error('login')): ?>
            <div class="alert"><?= $v->icon('flag') ?><?= $v->e($message) ?></div>
        <?php endif; ?>

        <form action="<?= $v->url('/login') ?>" method="post" class="stack" novalidate>
            <?= $v->csrfField() ?>
            <label>
                <span>Email</span>
                <input type="email" name="email" required autocomplete="email" autofocus
                       value="<?= $v->e($v->old('email')) ?>" aria-invalid="<?= $v->invalid('email') ?>">
                <?= $view->partial('partials/field_error', ['field' => 'email']) ?>
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" required autocomplete="current-password"
                       aria-invalid="<?= $v->invalid('password') ?>">
                <?= $view->partial('partials/field_error', ['field' => 'password']) ?>
            </label>
            <button class="btn btn-primary btn-block" type="submit">Log in</button>
        </form>

        <p class="muted small center">New here? <a href="<?= $v->url('/register') ?>">Create an account</a></p>
    </section>
</main>
