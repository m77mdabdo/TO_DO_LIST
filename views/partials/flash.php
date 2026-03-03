<?php
/**
 * @var App\View\ViewHelpers $v
 * @var bool $inlineErrors pages that show errors next to each field skip the error toast
 */
$success = $v->flash('success');
$errors  = $v->errors();
?>
<div class="toasts" aria-live="polite">
    <?php if ($success): ?>
        <div class="toast toast-success"><?= $v->icon('check') ?><span><?= $v->e($success) ?></span></div>
    <?php endif; ?>
    <?php if ($errors && !$inlineErrors): ?>
        <div class="toast toast-error"><?= $v->icon('flag') ?><span><?= $v->e(reset($errors)) ?></span></div>
    <?php endif; ?>
</div>
