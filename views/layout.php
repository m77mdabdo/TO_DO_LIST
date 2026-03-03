<?php
/**
 * @var App\View\ViewHelpers $v
 * @var string $appName
 * @var string $content
 * @var string|null $title
 * @var string|null $bodyClass
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $v->csrfToken() ?>">
    <meta name="theme-color" content="#7c5cff">
    <title><?= $v->e(isset($title) ? "$title · $appName" : $appName) ?></title>
    <link rel="icon" href="<?= $v->asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= $v->asset('css/style.css') ?>">
    <script src="<?= $v->asset('js/theme.js') ?>"></script>
</head>
<body class="<?= $v->e($bodyClass ?? '') ?>">
<div class="blobs" aria-hidden="true"><span></span><span></span><span></span></div>
<?= $view->partial('partials/flash', ['inlineErrors' => $inlineErrors ?? false]) ?>
<?= $content ?>
<script src="<?= $v->asset('js/app.js') ?>" defer></script>
</body>
</html>
