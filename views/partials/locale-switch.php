<?php /** @var \App\View\View $this */ $other = $this->locale() === 'ar' ? 'en' : 'ar'; ?>
<form class="locale-switch" action="<?= $this->url('/locale/' . $other) ?>" method="post">
    <?= $this->csrfField() ?>
    <button class="btn btn-ghost" type="submit" lang="<?= $other ?>"><?= $other === 'ar' ? 'العربية' : 'English' ?></button>
</form>
