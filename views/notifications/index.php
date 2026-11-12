<?php
/**
 * @var \App\View\View $this
 * @var list<\App\Modules\Notification\Contract\Notification> $notifications
 * @var bool $hasMore
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var bool $emailEnabled
 * @var int $unread
 * @var \App\Support\TimeAgo $timeAgo
 */
use App\Modules\Notification\Contract\Messages;

// Translate the sentence, then put the names in as isolated spans (like the activity feed).
$name = fn (string $value): string => '<bdi class="activity-name">' . $this->e($value) . '</bdi>';
$sentence = function (\App\Modules\Notification\Contract\Notification $n) use ($people, $name): string {
    $replace = [
        ':actor'   => $name($n->actorId !== null ? ($people[$n->actorId]->name ?? $this->t('Someone')) : $this->t('Someone')),
        ':subject' => $name($n->subject),
    ];
    foreach ($n->data as $key => $value) {
        if ($key !== 'excerpt') {
            $replace[':' . $key] = $name($key === 'role' ? $this->t($value) : $value);
        }
    }

    return strtr($this->e($this->t(Messages::sentence($n->type))), $replace);
};
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow stack">
    <section class="card stack">
        <div class="section-head">
            <h1><?= $this->icon('bell') ?> <?= $this->te('Notifications') ?></h1>
            <?php if ($unread > 0): ?>
                <form action="<?= $this->url('/notifications/read-all') ?>" method="post">
                    <?= $this->csrfField() ?>
                    <button class="btn btn-ghost" type="submit"><?= $this->icon('check') ?> <?= $this->te('Mark all as read') ?></button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$notifications): ?>
            <p class="muted"><?= $this->te('Nothing yet. You will hear here when someone mentions you, assigns you, or a card you watch changes.') ?></p>
        <?php endif; ?>

        <ul class="notification-list">
            <?php foreach ($notifications as $n): ?>
                <li class="notification <?= $n->isRead() ? '' : 'is-unread' ?>">
                    <form action="<?= $this->url('/notifications/' . $n->id . '/open') ?>" method="post">
                        <?= $this->csrfField() ?>
                        <button type="submit" class="notification-open">
                            <span class="notification-text"><?= $sentence($n) ?></span>
                            <?php if (isset($n->data['excerpt']) && $n->data['excerpt'] !== ''): ?>
                                <span class="notification-excerpt muted" dir="auto"><?= $this->e($n->data['excerpt']) ?></span>
                            <?php endif; ?>
                            <small class="muted"><?= $this->e($timeAgo->format(new DateTimeImmutable($n->createdAt))) ?></small>
                        </button>
                    </form>
                    <?php if (!$n->isRead()): ?><span class="unread-dot" title="<?= $this->te('Unread') ?>"></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($hasMore): ?>
            <a class="btn btn-ghost" href="<?= $this->url('/notifications?before=' . end($notifications)->id) ?>"><?= $this->te('Older notifications') ?></a>
        <?php endif; ?>
    </section>

    <section class="card stack">
        <h2 class="section-title"><?= $this->icon('mail') ?> <?= $this->te('Email') ?></h2>
        <form action="<?= $this->url('/notifications/settings') ?>" method="post" class="stack-sm">
            <?= $this->csrfField() ?>
            <label class="check"><input type="checkbox" name="email" value="1" <?= $emailEnabled ? 'checked' : '' ?>> <?= $this->te('Email me when I am mentioned or assigned, when a due date comes close, and when my access changes') ?></label>
            <div><button class="btn btn-ghost" type="submit"><?= $this->te('Save') ?></button></div>
        </form>
    </section>
</main>
