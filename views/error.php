<?php
/**
 * @var App\View\ViewHelpers $v
 * @var int $status
 * @var string $message
 */
?>
<main class="error-shell">
    <div class="card error-card">
        <p class="error-code gradient-text"><?= (int) $status ?></p>
        <h1><?= $status === 404 ? 'Lost in the list' : 'Something went wrong' ?></h1>
        <p class="muted"><?= $v->e($message) ?></p>
        <a class="btn btn-primary" href="<?= $v->url('/') ?>"><?= $v->icon('left') ?> Back to the board</a>
    </div>
</main>
