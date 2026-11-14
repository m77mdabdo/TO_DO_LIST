<?php
/**
 * Watch toggle on the card page (slot "card.side").
 *
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var bool $watching
 */
?>
<section class="card stack-sm watch-box">
    <form action="<?= $this->url('/cards/' . $card->publicId . '/watch') ?>" method="post">
        <?= $this->csrfField() ?>
        <button class="btn btn-block <?= $watching ? 'btn-primary' : 'btn-ghost' ?>" type="submit" aria-pressed="<?= $watching ? 'true' : 'false' ?>">
            <?= $this->icon('eye') ?> <?= $this->te($watching ? 'Watching' : 'Watch') ?>
        </button>
    </form>
    <p class="muted small"><?= $this->te($watching ? 'You get notified about comments, due dates and moves on this card.' : 'Get notified about comments, due dates and moves on this card.') ?></p>
</section>
