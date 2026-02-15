<?php

if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/App.php';
    http_response_code(404);
}

$errorCode    ??= 404;
$errorMessage ??= "This page doesn't exist.";
$inlineErrors = true;

$pageTitle = (string) $errorCode;
$bodyClass = 'page-error';
require __DIR__ . '/inc/header.php';
?>

<main class="error-shell">
    <div class="card error-card">
        <p class="error-code gradient-text"><?= (int) $errorCode ?></p>
        <h1><?= $errorCode === 404 ? 'Lost in the list' : 'Something went wrong' ?></h1>
        <p class="muted"><?= e($errorMessage) ?></p>
        <a class="btn btn-primary" href="<?= url('index.php') ?>"><?= icon('left') ?> Back to the board</a>
    </div>
</main>

<?php require __DIR__ . '/inc/footer.php'; ?>
