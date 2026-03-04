<?php
/**
 * @var App\View\ViewHelpers $v
 * @var App\View\View $view
 * @var string $appName
 */
$fields = [
    // name, label, type, autocomplete
    ['name', 'Name', 'text', 'name'],
    ['email', 'Email', 'email', 'email'],
    ['password', 'Password', 'password', 'new-password'],
    ['password_confirmation', 'Confirm password', 'password', 'new-password'],
];
?>
<main class="auth">
    <?= $view->partial('partials/auth_art', [
        'cards'     => [['mint', 'Read 20 pages'], ['coral', 'Pay the bills'], 'Call mom'],
        'headline'  => 'Get your day',
        'highlight' => 'out of your head.',
        'text'      => 'Free, private and fast. Your tasks stay in your own database.',
    ]) ?>

    <section class="card auth-card">
        <a class="brand" href="<?= $v->url('/login') ?>">
            <span class="brand-mark"><?= $v->icon('check') ?></span><span><?= $v->e($appName) ?></span>
        </a>
        <h1>Create your account</h1>
        <p class="muted">It takes less than a minute.</p>

        <form action="<?= $v->url('/register') ?>" method="post" class="stack" novalidate>
            <?= $v->csrfField() ?>
            <?php foreach ($fields as [$field, $label, $type, $autocomplete]): ?>
                <label>
                    <span><?= $label ?></span>
                    <input type="<?= $type ?>" name="<?= $field ?>" required autocomplete="<?= $autocomplete ?>"
                           <?= $type !== 'password' ? 'value="' . $v->e($v->old($field)) . '"' : '' ?>
                           <?= $field === 'name' ? 'dir="auto" autofocus' : '' ?>
                           aria-invalid="<?= $v->invalid($field) ?>">
                    <?= $view->partial('partials/field_error', ['field' => $field]) ?>
                </label>
            <?php endforeach; ?>
            <button class="btn btn-primary btn-block" type="submit">Create account</button>
        </form>

        <p class="muted small center">Already have an account? <a href="<?= $v->url('/login') ?>">Log in</a></p>
    </section>
</main>
