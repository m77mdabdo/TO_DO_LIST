<?php
/**
 * @var \App\View\View $this
 * @var list<\App\Modules\Notification\Contract\Notification> $notifications
 * @var bool $hasMore
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var bool $emailEnabled
 * @var array{email: bool, muted: list<string>, emailOff: list<string>} $preferences
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

    <section class="card stack" id="email-settings" aria-labelledby="email-settings-title">
        <h2 class="section-title" id="email-settings-title"><?= $this->icon('mail') ?> <?= $this->te('What to tell me') ?></h2>
        <form action="<?= $this->url('/notifications/settings') ?>" method="post" class="stack">
            <?= $this->csrfField() ?>
            <input type="hidden" name="detailed" value="1">
            <label class="check"><input type="checkbox" name="email" value="1" <?= $emailEnabled ? 'checked' : '' ?>> <?= $this->te('Email me when I am mentioned or assigned, when a due date comes close, and when my access changes') ?></label>
            <div class="table-wrap">
                <table class="data-table">
                    <caption class="visually-hidden"><?= $this->te('Choices for each kind of notification') ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?= $this->te('When') ?></th>
                            <th scope="col"><?= $this->te('In the app') ?></th>
                            <th scope="col"><?= $this->te('Email') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (\App\Modules\Notification\Contract\Messages::LABELS as $type => $text): ?>
                            <?php $always = in_array($type, \App\Modules\Notification\Contract\Messages::ALWAYS_SHOWN, true); $mailable = in_array($type, \App\Modules\Notification\Contract\Messages::EMAILED, true); ?>
                            <tr>
                                <th scope="row"><?= $this->te($text) ?></th>
                                <td><label class="check"><input type="checkbox" name="shown[]" value="<?= $this->e($type) ?>" <?= $always || !in_array($type, $preferences['muted'], true) ? 'checked' : '' ?> <?= $always ? 'disabled' : '' ?>>
                                    <span class="visually-hidden"><?= $this->te('In the app') ?>: <?= $this->te($text) ?></span></label></td>
                                <td><?php if ($mailable): ?><label class="check"><input type="checkbox" name="emailed[]" value="<?= $this->e($type) ?>" <?= !in_array($type, $preferences['emailOff'], true) ? 'checked' : '' ?>>
                                    <span class="visually-hidden"><?= $this->te('Email') ?>: <?= $this->te($text) ?></span></label><?php else: ?><span class="muted" aria-label="<?= $this->te('Not emailed') ?>">—</span><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted small"><?= $this->te('Changes to your own access always show in the app.') ?></p>
            <div><button class="btn btn-ghost" type="submit"><?= $this->te('Save') ?></button></div>
        </form>
    </section>

    <section class="card stack js-push" hidden aria-labelledby="push-title"
             data-on="<?= $this->te('Browser notifications are on in this browser.') ?>"
             data-off="<?= $this->te('Browser notifications are off in this browser.') ?>"
             data-blocked="<?= $this->te('This browser blocks notifications from this site. Allow them in the browser settings, then try again.') ?>"
             data-unsupported="<?= $this->te('This browser cannot show notifications.') ?>">
        <h2 class="section-title" id="push-title"><?= $this->icon('bell') ?> <?= $this->te('Browser notifications') ?></h2>
        <p class="muted small"><?= $this->te('Get a pop-up from your browser when something new arrives here, while this site is open in a tab. Only this browser is affected.') ?></p>
        <p class="js-push-state" role="status"></p>
        <div class="form-actions">
            <button class="btn btn-primary js-push-on" type="button"><?= $this->te('Turn on') ?></button>
            <button class="btn btn-ghost js-push-off" type="button"><?= $this->te('Turn off') ?></button>
        </div>
    </section>
</main>
