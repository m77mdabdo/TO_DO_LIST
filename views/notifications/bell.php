<?php
/**
 * The bell in the header (slot "header.actions").
 *
 * @var \App\View\View $this
 * @var int $unread
 */
$label = $unread > 0 ? $this->t(':count unread notifications', ['count' => (string) $unread]) : $this->t('Notifications');
?>
<a class="icon-btn bell" href="<?= $this->url('/notifications') ?>" title="<?= $this->e($label) ?>" aria-label="<?= $this->e($label) ?>">
    <?= $this->icon('bell') ?>
    <?php if ($unread > 0): ?><span class="bell-count" aria-hidden="true"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
</a>
