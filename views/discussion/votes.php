<?php
/**
 * Votes on the card page (slot "card.side", filled by the Discussion module).
 *
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var array<int, \App\Modules\Identity\Contract\User> $voters
 * @var bool $voted
 * @var bool $canVote
 * @var bool $votingOn
 */
if (!$votingOn && !$voters) {
    return;
}
?>
<section class="card stack votes">
    <h2 class="section-title"><?= $this->icon('vote') ?> <?= $this->te('Votes') ?> <span class="count"><?= count($voters) ?></span></h2>
    <?php if ($canVote): ?>
        <form action="<?= $this->url('/cards/' . $card->publicId . '/vote') ?>" method="post">
            <?= $this->csrfField() ?>
            <button class="btn btn-block <?= $voted ? 'btn-primary' : 'btn-ghost' ?>" type="submit" aria-pressed="<?= $voted ? 'true' : 'false' ?>">
                <?= $this->icon('vote') ?><?= $this->te($voted ? 'Remove my vote' : 'Vote for this card') ?>
            </button>
        </form>
    <?php endif; ?>
    <?php if ($voters): ?>
        <ul class="people-list">
            <?php foreach ($voters as $voter): ?>
                <li><span class="avatar avatar-sm" aria-hidden="true"><?= $this->e(mb_strtoupper(mb_substr($voter->name, 0, 1))) ?></span><bdi><?= $this->e($voter->name) ?></bdi></li>
            <?php endforeach; ?>
        </ul>
    <?php elseif (!$canVote): ?>
        <p class="muted small"><?= $this->te('No votes yet.') ?></p>
    <?php endif; ?>
</section>
