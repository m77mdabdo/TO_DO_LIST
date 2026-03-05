<?php
/**
 * Autocomplete for the "List" inputs.
 *
 * @var App\View\ViewHelpers $v
 * @var list<string> $categories
 */
?>
<datalist id="category-list">
    <?php foreach ($categories as $category): ?>
        <option value="<?= $v->e($category) ?>">
    <?php endforeach; ?>
</datalist>
