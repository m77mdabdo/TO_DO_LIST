<?php
/**
 * The board's card buttons on the card page (slot "card.side").
 *
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var list<\App\Modules\Automation\Contract\Automation> $buttons
 */
if ($buttons === []) {
    return;
}
?>
<section class="card stack-sm card-buttons" aria-labelledby="card-buttons-title">
    <h2 class="section-title" id="card-buttons-title"><?= $this->icon('bolt') ?> <?= $this->te('Buttons') ?></h2>
    <?php foreach ($buttons as $button): ?>
        <form action="<?= $this->url('/cards/' . $card->publicId . '/buttons/' . $button->publicId) ?>" method="post">
            <?= $this->csrfField() ?>
            <button class="btn btn-ghost btn-block" type="submit"><?= $this->icon('play') ?> <span dir="auto"><?= $this->e($button->name) ?></span></button>
        </form>
    <?php endforeach; ?>
</section>
