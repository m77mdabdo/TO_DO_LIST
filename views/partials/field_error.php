<?php
/**
 * @var App\View\ViewHelpers $v
 * @var string $field
 */
if ($message = $v->error($field)): ?>
    <small class="field-error"><?= $v->e($message) ?></small>
<?php endif;
