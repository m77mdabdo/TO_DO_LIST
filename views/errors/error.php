<?php /** @var \App\View\View $this @var int $status @var string $message */ ?>
<main class="error-shell">
    <div class="card error-card">
        <p class="error-code gradient-text"><?= (int) $status ?></p>
        <h1><?= $this->te(match ($status) { 404 => 'Lost in the list', 429 => 'Slow down a little', default => 'Something went wrong' }) ?></h1>
        <p class="muted"><?= $this->te($message) ?></p>
        <a class="btn btn-primary" href="<?= $this->url('/') ?>"><?= $this->icon('left') ?> <?= $this->te('Back to the board') ?></a>
    </div>
</main>
