<?php /** Slot "account.settings": where the notification choices live. @var \App\View\View $this */ ?>
<section class="card stack" aria-labelledby="account-notifications-title">
    <h2 id="account-notifications-title"><?= $this->te('Notifications') ?></h2>
    <p><?= $this->te('Choose which kinds of notification you see in the app and which ones are emailed.') ?></p>
    <div><a class="btn btn-ghost" href="<?= $this->url('/notifications#email-settings') ?>"><?= $this->icon('bell') ?><?= $this->te('Notification choices') ?></a></div>
</section>
