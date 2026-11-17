<?php
/**
 * Cards linked to this one (both ways). Details of a linked card are only
 * shown when the viewer can open its board; otherwise just that it exists.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var list<array{id: int, card: ?\App\Modules\Card\Contract\CardSummary, board: ?string, list: ?string}> $cardLinks
 * @var bool $canEdit
 */
$form = $this->form;
?>
<section class="card stack" id="linked-cards" aria-labelledby="linked-cards-title">
    <h2 id="linked-cards-title" class="section-title"><?= $this->icon('link') ?> <?= $this->te('Linked cards') ?></h2>
    <?php if ($cardLinks === []): ?>
        <p class="muted small"><?= $this->te('No linked cards.') ?></p>
    <?php else: ?>
        <ul class="linked-cards">
            <?php foreach ($cardLinks as $link): ?>
                <li>
                    <?php if ($link['card'] !== null): ?>
                        <a class="card-chip<?= $link['card']->isComplete() ? ' is-done' : '' ?>" href="<?= $this->url('/cards/' . $link['card']->publicId) ?>">
                            <?= $this->icon($link['card']->isComplete() ? 'check' : 'board') ?>
                            <span class="card-chip-title" dir="auto"><?= $this->e($link['card']->title) ?></span>
                            <small class="muted" dir="auto"><?= $this->e($link['board']) ?> · <?= $this->e($link['list']) ?></small>
                        </a>
                    <?php else: ?>
                        <span class="card-chip is-locked"><?= $this->icon('lock') ?> <?= $this->te('A card you cannot open.') ?></span>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                        <form action="<?= $this->url('/cards/' . $card->publicId . '/connections/' . $link['id'] . '/delete') ?>" method="post">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-ghost btn-icon" type="submit" aria-label="<?= $this->te('Remove link') ?>" title="<?= $this->te('Remove link') ?>"><?= $this->icon('close') ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($canEdit): ?>
        <form action="<?= $this->url('/cards/' . $card->publicId . '/connections') ?>" method="post" class="stack-sm">
            <?= $this->csrfField() ?>
            <label>
                <span><?= $this->te('Link a card') ?></span>
                <input name="target" dir="ltr" required maxlength="300" value="<?= $this->e($form->old('target')) ?>"
                       placeholder="<?= $this->te('Paste the card address') ?>" <?= $form->error('link') ? 'aria-invalid="true" aria-describedby="link-error"' : '' ?>>
            </label>
            <?= $this->partial('partials/field-error', ['field' => 'link']) ?>
            <button class="btn btn-ghost" type="submit"><?= $this->icon('plus') ?><?= $this->te('Link') ?></button>
            <p class="muted small"><?= $this->te('Any card of this workspace that you can open. Pasting a card address in a description or comment shows it the same way.') ?></p>
        </form>
    <?php endif; ?>
</section>
