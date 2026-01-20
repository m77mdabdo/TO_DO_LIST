<?php

use Classes\Session;

$success = Session::pull('success');
$errorList = errors();
?>
<div class="toasts" aria-live="polite">
    <?php if ($success): ?>
        <div class="toast toast-success"><?= icon('check') ?><span><?= e($success) ?></span></div>
    <?php endif; ?>
    <?php if ($errorList && empty($inlineErrors)): ?>
        <div class="toast toast-error"><?= icon('flag') ?><span><?= e(reset($errorList)) ?></span></div>
    <?php endif; ?>
</div>
