<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Identity\Contract\User $user
 * @var list<array{type: string, ip: string, detail: string, created_at: string}> $events
 * @var \App\Support\TimeAgo $timeAgo
 * @var bool $twoFactorOn
 * @var bool $available APP_KEY is set, so a secret can be stored encrypted
 * @var ?string $setupKey the new secret, grouped, while setting up
 * @var ?string $setupUri otpauth:// link for the same secret
 * @var int $codesLeft
 * @var list<string> $freshCodes recovery codes, shown once
 * @var list<array{id: int, ip: string, agent: string, createdAt: string, lastSeenAt: string, current: bool}> $sessions
 */
$form = $this->form;
$browser = static function (string $agent): string {
    $name = match (true) {
        str_contains($agent, 'Edg/')     => 'Edge',
        str_contains($agent, 'OPR/')     => 'Opera',
        str_contains($agent, 'Firefox/') => 'Firefox',
        str_contains($agent, 'Chrome/')  => 'Chrome',
        str_contains($agent, 'Safari/')  => 'Safari',
        default                          => '',
    };
    $system = match (true) {
        str_contains($agent, 'Android')                                   => 'Android',
        str_contains($agent, 'iPhone') || str_contains($agent, 'iPad')    => 'iOS',
        str_contains($agent, 'Windows')                                   => 'Windows',
        str_contains($agent, 'Mac OS')                                    => 'macOS',
        str_contains($agent, 'Linux')                                     => 'Linux',
        default                                                           => '',
    };

    return trim($name . ($name !== '' && $system !== '' ? ' · ' : '') . $system);
};
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
    'two_factor_enabled'       => $this->t('Two-step sign-in turned on'),
    'two_factor_disabled'      => $this->t('Two-step sign-in turned off'),
    'two_factor_failed'        => $this->t('Wrong two-step code'),
    'signed_out_others'        => $this->t('Other devices signed out'),
    'signed_out_everywhere'    => $this->t('Signed out everywhere'),
    'data_exported'            => $this->t('Data downloaded'),
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
        <p><a href="<?= $this->url('/account') ?>"><?= $this->te('Name, language, your data and deleting the account') ?></a></p>
    </section>

    <section class="card stack" id="two-factor" aria-labelledby="two-factor-title">
        <h2 id="two-factor-title"><?= $this->te('Two-step sign-in') ?>
            <?php if ($twoFactorOn): ?><span class="pill pill-ok"><?= $this->te('On') ?></span><?php else: ?><span class="pill"><?= $this->te('Off') ?></span><?php endif; ?></h2>
        <p class="muted"><?= $this->te('After your password, you also type a code from an authenticator app on your phone (Google Authenticator, Microsoft Authenticator, 1Password, Aegis…).') ?></p>
        <?= $this->partial('partials/field-error', ['field' => 'code']) ?>
        <?= $this->partial('partials/field-error', ['field' => 'password']) ?>

        <?php if ($freshCodes !== []): ?>
            <div class="invite-link stack" role="status">
                <p><strong><?= $this->te('Save these recovery codes somewhere safe.') ?></strong> <?= $this->te('Each one works once if you lose your phone. They are shown only now.') ?></p>
                <ul class="recovery-codes" dir="ltr">
                    <?php foreach ($freshCodes as $code): ?><li><code><?= $this->e($code) ?></code></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($twoFactorOn): ?>
            <p class="small"><?= $this->te(':count recovery codes left.', ['count' => (string) $codesLeft]) ?></p>
            <div class="field-row">
                <form action="<?= $this->url('/account/two-factor/recovery') ?>" method="post" class="stack-sm">
                    <?= $this->csrfField() ?>
                    <label><span><?= $this->te('Your password') ?></span><input type="password" name="password" required autocomplete="current-password"></label>
                    <div><button class="btn btn-ghost" type="submit"><?= $this->te('New recovery codes') ?></button></div>
                </form>
                <form action="<?= $this->url('/account/two-factor/disable') ?>" method="post" class="stack-sm"
                      data-confirm="<?= $this->te('Turn off two-step sign-in? Your password alone will be enough to sign in.') ?>">
                    <?= $this->csrfField() ?>
                    <label><span><?= $this->te('Your password') ?></span><input type="password" name="password" required autocomplete="current-password"></label>
                    <div><button class="btn btn-danger-ghost" type="submit"><?= $this->te('Turn off') ?></button></div>
                </form>
            </div>
        <?php elseif (!$available): ?>
            <p class="alert"><?= $this->te('Two-step sign-in needs APP_KEY in the .env file. Ask whoever runs the app to add one (php bin/app-key.php).') ?></p>
        <?php elseif ($setupKey === null): ?>
            <form action="<?= $this->url('/account/two-factor/start') ?>" method="post">
                <?= $this->csrfField() ?>
                <button class="btn btn-primary" type="submit"><?= $this->icon('shield') ?><?= $this->te('Set up two-step sign-in') ?></button>
            </form>
        <?php else: ?>
            <ol class="stack-sm">
                <li><?= $this->te('In your authenticator app, add an account and choose to enter a setup key.') ?>
                    <p><?= $this->te('Setup key') ?>: <code class="setup-key" dir="ltr"><?= $this->e($setupKey) ?></code></p>
                    <p class="small"><a href="<?= $this->e((string) $setupUri) ?>"><?= $this->te('On this phone? Open the key in your app') ?></a></p>
                </li>
                <li><?= $this->te('Type the 6-digit code the app shows.') ?>
                    <form action="<?= $this->url('/account/two-factor/confirm') ?>" method="post" class="field-row">
                        <?= $this->csrfField() ?>
                        <label><span class="visually-hidden"><?= $this->te('Code') ?></span>
                            <input name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="7" dir="ltr" placeholder="123456"
                                   aria-invalid="<?= $form->error('code') ? 'true' : 'false' ?>"></label>
                        <button class="btn btn-primary" type="submit"><?= $this->te('Turn on') ?></button>
                    </form>
                </li>
            </ol>
        <?php endif; ?>
    </section>

    <section class="card stack" id="devices" aria-labelledby="devices-title">
        <h2 id="devices-title"><?= $this->te('Signed-in devices') ?> <span class="muted">(<?= count($sessions) ?>)</span></h2>
        <ul class="member-list">
            <?php foreach ($sessions as $session): ?>
                <li>
                    <span class="avatar avatar-muted" aria-hidden="true"><?= $this->icon('key') ?></span>
                    <span class="member-name">
                        <strong><?= $this->e($browser($session['agent']) ?: $this->t('Unknown browser')) ?><?php if ($session['current']): ?> <small class="pill pill-ok"><?= $this->te('This device') ?></small><?php endif; ?></strong>
                        <small class="muted"><bdi dir="ltr"><?= $this->e($session['ip'] ?: '—') ?></bdi> · <?= $this->te('last seen :when', ['when' => $timeAgo->format(new DateTimeImmutable($session['lastSeenAt']))]) ?></small>
                    </span>
                    <?php if (!$session['current']): ?>
                        <form action="<?= $this->url('/account/sessions/' . $session['id']) ?>" method="post">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-ghost" type="submit"><?= $this->te('Sign out') ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="form-actions">
            <form action="<?= $this->url('/account/sessions/others') ?>" method="post">
                <?= $this->csrfField() ?>
                <button class="btn btn-ghost" type="submit"><?= $this->te('Sign out every other device') ?></button>
            </form>
            <form action="<?= $this->url('/account/sessions/all') ?>" method="post" data-confirm="<?= $this->te('Sign out everywhere, this device too?') ?>">
                <?= $this->csrfField() ?>
                <button class="btn btn-danger-ghost" type="submit"><?= $this->te('Sign out everywhere') ?></button>
            </form>
        </div>
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
