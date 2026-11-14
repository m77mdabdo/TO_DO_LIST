<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Identity\Contract\User $user
 * @var list<array{type: string, ip: string, detail: string, created_at: string}> $events
 * @var \App\Support\TimeAgo $timeAgo
 */
$label = fn (string $type): string => match ($type) {
    'registered'               => $this->t('Account created'),
    'login'                    => $this->t('Signed in'),
    'login_failed'             => $this->t('Failed sign-in'),
    'logout'                   => $this->t('Signed out'),
    'password_reset_requested' => $this->t('Password reset link requested'),
    'password_reset'           => $this->t('Password changed with a reset link'),
    'password_changed'         => $this->t('Password changed'),
    'verification_sent'        => $this->t('Confirmation email sent'),
    'email_verified'           => $this->t('Email confirmed'),
    'rate_limited'             => $this->t('Too many requests refused'),
    default                    => $type,
};
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow stack">
    <section class="card stack">
        <div class="section-head">
            <h1><?= $this->icon('key') ?> <?= $this->te('Account security') ?></h1>
        </div>

        <dl class="account-facts">
            <dt><?= $this->te('Email') ?></dt>
            <dd>
                <bdi><?= $this->e($user->email) ?></bdi>
                <?php if ($user->emailVerified): ?>
                    <span class="pill pill-ok"><?= $this->te('Confirmed') ?></span>
                <?php else: ?>
                    <span class="pill pill-warn"><?= $this->te('Not confirmed yet') ?></span>
                <?php endif; ?>
            </dd>
            <dt><?= $this->te('Password') ?></dt>
            <dd>
                <?= $this->te('To change it, sign out and use “Forgot your password?”. Changing it signs out every other device.') ?>
            </dd>
        </dl>
    </section>

    <section class="card stack" aria-labelledby="security-log-title">
        <h2 id="security-log-title"><?= $this->te('Recent security events') ?></h2>
        <?php if (!$events): ?>
            <p class="muted"><?= $this->te('Nothing recorded yet.') ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= $this->te('Event') ?></th>
                            <th scope="col"><?= $this->te('IP address') ?></th>
                            <th scope="col"><?= $this->te('When') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?= $this->e($label($event['type'])) ?></td>
                                <td><bdi dir="ltr"><?= $this->e($event['ip']) ?></bdi></td>
                                <td><time datetime="<?= $this->e($event['created_at']) ?>"><?= $this->e($timeAgo->format(new DateTimeImmutable($event['created_at']))) ?></time></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted small"><?= $this->te('If you see a sign-in you do not recognise, change your password right away.') ?></p>
        <?php endif; ?>
    </section>
</main>
