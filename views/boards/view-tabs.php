<?php
/**
 * Switches between the ways of looking at a board. Filters carry over.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var string $current "", "/table", "/calendar" or "/dashboard"
 * @var array<string, string> $query the current query string
 */
$base = '/boards/' . $board->publicId;
$keep = array_intersect_key($query ?? [], array_flip(['q', 'label', 'member', 'due', 'status']));
$suffix = $keep ? '?' . http_build_query($keep) : '';
$tabs = [
    ''           => ['board', 'Board'],
    '/table'     => ['table', 'Table'],
    '/calendar'  => ['calendar', 'Calendar'],
    '/dashboard' => ['chart', 'Dashboard'],
];
?>
<nav class="view-tabs" aria-label="<?= $this->te('Board views') ?>">
    <?php foreach ($tabs as $path => [$icon, $label]): ?>
        <a href="<?= $this->e($this->url($base . $path . ($path === '/dashboard' ? '' : $suffix))) ?>" class="<?= $path === $current ? 'is-active' : '' ?>" <?= $path === $current ? 'aria-current="page"' : '' ?>><?= $this->icon($icon) ?><span><?= $this->te($label) ?></span></a>
    <?php endforeach; ?>
</nav>
