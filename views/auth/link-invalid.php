<?php
/**
 * @var \App\View\View $this
 * @var string $appName
 * @var string $message
 * @var array{0: string, 1: string}|null $action path and label
 */
$resend ??= false;
?>
<main class="auth auth-single">
    <section class="card auth-card">
        <a class="brand" href="<?= $this->url('/') ?>">
            <span class="brand-mark"><?= $this->icon('check') ?></span><span><?= $this->e($appName) ?></span>
        </a>
        <h1><?= $this->te('This link no longer works') ?></h1>
        <p class="muted" role="alert"><?= $this->te($message) ?></p>

        <?php if ($resend): ?>
            <form action="<?= $this->url('/email/verification') ?>" method="post">
                <?= $this->csrfField() ?>
                <button class="btn btn-primary btn-block" type="submit"><?= $this->te('Send a new confirmation link') ?></button>
            </form>
        <?php elseif ($action): ?>
            <a class="btn btn-primary btn-block" href="<?= $this->url($action[0]) ?>"><?= $this->te($action[1]) ?></a>
        <?php endif; ?>
    </section>
</main>
