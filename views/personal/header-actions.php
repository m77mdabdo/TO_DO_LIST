<?php
/**
 * Header buttons from the Personal module: quick capture into the inbox
 * (also the "q" key), the inbox and the planner.
 *
 * @var \App\View\View $this
 * @var int $inboxCount
 */
?>
<details class="menu quick-capture js-quick-capture">
    <summary class="icon-btn" title="<?= $this->te('Add to your inbox') ?> (Q)" aria-label="<?= $this->te('Add to your inbox') ?>"><?= $this->icon('plus') ?></summary>
    <div class="menu-panel stack-sm">
        <form action="<?= $this->url('/inbox') ?>" method="post" class="stack-sm js-capture-form">
            <?= $this->csrfField() ?>
            <label><span><?= $this->te('Add to your inbox') ?></span>
                <input type="text" name="title" maxlength="200" required dir="auto" autocomplete="off" placeholder="<?= $this->te('Something to do or remember') ?>"></label>
            <small class="muted"><?= $this->te('Only you see your inbox. Move items to a board when you are ready.') ?></small>
            <div class="row-actions">
                <button class="btn btn-primary" type="submit"><?= $this->te('Add') ?></button>
                <a class="btn btn-ghost" href="<?= $this->url('/inbox') ?>"><?= $this->te('Open inbox') ?></a>
            </div>
        </form>
    </div>
</details>
<a class="icon-btn has-count" href="<?= $this->url('/inbox') ?>" title="<?= $this->te('Inbox') ?>" aria-label="<?= $this->te('Inbox') ?><?= $inboxCount > 0 ? ' (' . $inboxCount . ')' : '' ?>">
    <?= $this->icon('inbox') ?><?php if ($inboxCount > 0): ?><span class="icon-count js-inbox-count" aria-hidden="true"><?= $inboxCount > 99 ? '99+' : $inboxCount ?></span><?php endif; ?>
</a>
<a class="icon-btn hide-sm" href="<?= $this->url('/planner') ?>" title="<?= $this->te('Planner') ?>" aria-label="<?= $this->te('Planner') ?>"><?= $this->icon('clock') ?></a>
