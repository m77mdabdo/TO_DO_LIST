<?php /** @var \App\View\View $this @var \App\Modules\Identity\Contract\User $user */ ?>
<?php if (!$user->emailVerified): ?>
    <div class="banner banner-verify" role="status">
        <?= $this->icon('flag') ?>
        <span><?= $this->te('Please confirm your email :email. Until then you cannot invite people or create API tokens.', ['email' => $user->email]) ?></span>
        <form action="<?= $this->url('/email/verification') ?>" method="post">
            <?= $this->csrfField() ?>
            <button class="btn btn-ghost" type="submit"><?= $this->te('Send the link again') ?></button>
        </form>
    </div>
<?php endif; ?>
