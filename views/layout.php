<?php
/**
 * @var \App\View\View $this
 * @var string $content
 * @var string $appName
 */
$title ??= '';
$success = $this->flash('success');
$errors = $this->form->errors();
?>
<!DOCTYPE html>
<html lang="<?= $this->e($this->locale()) ?>" dir="<?= $this->e($this->direction()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $this->csrfToken() ?>">
    <meta name="theme-color" content="#7c5cff">
    <title><?= $this->e($title ? $this->t($title) . " · $appName" : $appName) ?></title>
    <link rel="icon" href="<?= $this->asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= $this->asset('css/style.css') ?>">
    <script src="<?= $this->asset('js/theme.js') ?>"></script>
</head>
<body class="<?= $this->e($bodyClass ?? '') ?>">
<div class="blobs" aria-hidden="true"><span></span><span></span><span></span></div>

<div class="toasts" aria-live="polite">
    <?php if ($success): ?>
        <div class="toast toast-success"><?= $this->icon('check') ?><span><?= $this->te($success) ?></span></div>
    <?php endif; ?>
    <?php if ($errors && empty($inlineErrors)): ?>
        <div class="toast toast-error"><?= $this->icon('flag') ?><span><?= $this->te((string) reset($errors)) ?></span></div>
    <?php endif; ?>
</div>

<?= $content ?>

<?= $this->partial('partials/locale-switch') ?>

<script src="<?= $this->asset('js/app.js') ?>" defer></script>
</body>
</html>
