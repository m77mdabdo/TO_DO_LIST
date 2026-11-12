<?php
/**
 * The page an invitation link opens.
 *
 * @var \App\View\View $this
 * @var string $appName
 * @var array{invitation: \App\Modules\Invitation\Contract\Invitation, state: string, workspace: ?\App\Modules\Workspace\Contract\WorkspaceInfo, inviter: ?\App\Modules\Identity\Contract\User}|null $found
 * @var ?\App\Modules\Identity\Contract\User $user
 * @var string $token
 */
$valid = $found !== null && $found['state'] === 'pending' && $found['workspace'] !== null;
$mismatch = $valid && $user !== null && !$found['invitation']->isFor($user->email);
?>
<main class="auth">
    <section class="card auth-card invite-page stack">
        <a class="brand" href="<?= $this->url('/') ?>">
            <span class="brand-mark"><?= $this->icon('check') ?></span><span><?= $this->e($appName) ?></span>
        </a>

        <?php if (!$valid): ?>
            <h1><?= $this->te('This invitation link is no longer valid') ?></h1>
            <p class="muted"><?= $this->te($found !== null && $found['state'] === 'accepted'
                ? 'It was already used. If that was you, just log in.'
                : 'It may have expired or been cancelled. Ask the person who invited you for a new one.') ?></p>
            <div class="actions"><a class="btn btn-primary" href="<?= $this->url('/login') ?>"><?= $this->te('Log in') ?></a></div>
        <?php else: ?>
            <?php $invitation = $found['invitation']; ?>
            <h1><?= $this->te('You are invited to :company', [':company' => $found['workspace']->companyName]) ?></h1>
            <p class="muted">
                <?= $this->te(':name invited you as :role.', [
                    ':name' => $found['inviter']->name ?? $this->t('Someone'),
                    ':role' => $this->t($invitation->role->label()),
                ]) ?>
                <?php if ($invitation->boardIds): ?><?= $this->te('You will also join :count boards.', [':count' => (string) count($invitation->boardIds)]) ?><?php endif; ?>
            </p>
            <?= $this->partial('partials/field-error', ['field' => 'invitation']) ?>

            <?php if ($user === null): ?>
                <p class="small"><?= $this->te('Log in or create an account with :email to accept.', [':email' => $invitation->email]) ?></p>
                <div class="actions">
                    <a class="btn btn-primary" href="<?= $this->url('/register') ?>"><?= $this->te('Create an account') ?></a>
                    <a class="btn btn-ghost" href="<?= $this->url('/login') ?>"><?= $this->te('Log in') ?></a>
                </div>
            <?php elseif ($mismatch): ?>
                <div class="alert"><?= $this->icon('flag') ?><?= $this->te('This invitation is for :email, but you are signed in as :current.', [':email' => $invitation->email, ':current' => $user->email]) ?></div>
                <form action="<?= $this->url('/logout') ?>" method="post">
                    <?= $this->csrfField() ?>
                    <button class="btn btn-ghost" type="submit"><?= $this->te('Log out and use the right account') ?></button>
                </form>
            <?php else: ?>
                <form action="<?= $this->url('/invitations/' . $token . '/accept') ?>" method="post">
                    <?= $this->csrfField() ?>
                    <button class="btn btn-primary btn-block" type="submit"><?= $this->te('Join :company', [':company' => $found['workspace']->companyName]) ?></button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
