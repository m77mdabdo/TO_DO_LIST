<?php
/**
 * The colourful side panel on the login and sign-up pages.
 *
 * @var App\View\ViewHelpers $v
 * @var array{0: array{0: string, 1: string}, 1: array{0: string, 1: string}, 2: string} $cards
 * @var string $headline
 * @var string $highlight
 * @var string $text
 */
?>
<section class="auth-art" aria-hidden="true">
    <div class="floating-card fc-1"><span class="dot dot-<?= $cards[0][0] ?>"></span><?= $v->e($cards[0][1]) ?></div>
    <div class="floating-card fc-2"><span class="dot dot-<?= $cards[1][0] ?>"></span><?= $v->e($cards[1][1]) ?></div>
    <div class="floating-card fc-3"><?= $v->icon('check') ?><?= $v->e($cards[2]) ?></div>
    <h2><?= $v->e($headline) ?><br><span class="gradient-text"><?= $v->e($highlight) ?></span></h2>
    <p><?= $v->e($text) ?></p>
</section>
