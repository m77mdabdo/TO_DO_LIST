<?php /** @var \App\View\View $this @var list<string> $categories */ ?>
<datalist id="category-list">
    <?php foreach ($categories as $category): ?>
        <option value="<?= $this->e($category) ?>">
    <?php endforeach; ?>
</datalist>
