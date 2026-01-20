<?php
/** @var string $pageTitle */
/** @var string $bodyClass */
use Classes\Csrf;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= Csrf::token() ?>">
    <meta name="theme-color" content="#7c5cff">
    <title><?= e(($pageTitle ?? '') ? $pageTitle . ' · ' : '') . e($GLOBALS['config']['app_name'] ?? 'Todo Board') ?></title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <script src="<?= asset('js/theme.js') ?>"></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<div class="blobs" aria-hidden="true"><span></span><span></span><span></span></div>
<?php require __DIR__ . '/flash.php'; ?>
