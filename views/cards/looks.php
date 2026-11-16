<?php
/**
 * The card's look: cover, sticker and kind. Shown on the card page.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var list<\App\Modules\Attachment\Contract\Attachment> $coverImages the card's own images
 * @var list<\App\Modules\Board\Contract\BoardSummary> $linkBoards boards of the workspace the viewer can open
 * @var bool $canEdit
 */
use App\Modules\Card\Contract\CardSummary;

$cover = $card->coverColor !== null ? 'color:' . $card->coverColor : '';
foreach ($coverImages as $image) {
    if ($image->id === $card->coverAttachmentId) {
        $cover = 'image:' . $image->publicId;
    }
}
$form = $this->form;
?>
<section class="card stack" id="looks" aria-labelledby="looks-title">
    <h2 id="looks-title" class="section-title"><?= $this->icon('sparkle') ?> <?= $this->te('Look') ?></h2>
    <?php if (!$canEdit): ?>
        <p class="muted small"><?= $card->sticker !== null ? $this->e(CardSummary::STICKERS[$card->sticker][0] ?? '') . ' ' : '' ?><?= $this->te($card->hasCover() ? 'This card has a cover.' : 'No cover.') ?></p>
    <?php else: ?>
        <form action="<?= $this->url('/cards/' . $card->publicId . '/looks') ?>" method="post" class="stack-sm">
            <?= $this->csrfField() ?>
            <fieldset class="cover-choices">
                <legend><?= $this->te('Cover') ?></legend>
                <label class="cover-choice cover-none"><input type="radio" name="cover" value="" <?= $cover === '' ? 'checked' : '' ?>><span><?= $this->te('None') ?></span></label>
                <?php foreach (CardSummary::COVER_COLORS as $color): ?>
                    <label class="cover-choice tile-<?= $color ?>" title="<?= $this->te(ucfirst($color)) ?>">
                        <input type="radio" name="cover" value="color:<?= $color ?>" <?= $cover === 'color:' . $color ? 'checked' : '' ?>>
                        <span class="visually-hidden"><?= $this->te(ucfirst($color)) ?></span></label>
                <?php endforeach; ?>
                <?php foreach ($coverImages as $image): ?>
                    <label class="cover-choice cover-image">
                        <input type="radio" name="cover" value="image:<?= $this->e($image->publicId) ?>" <?= $cover === 'image:' . $image->publicId ? 'checked' : '' ?>>
                        <img src="<?= $this->url('/attachments/' . $image->publicId) ?>" alt="<?= $this->e($image->name) ?>" width="64" height="40" loading="lazy"></label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (!$coverImages): ?>
                <p class="muted small"><?= $this->te('Attach an image to this card to use it as the cover.') ?></p>
            <?php else: ?>
                <p class="muted small"><?= $this->te('If you remove the cover image from the attachments, the card simply goes back to no cover.') ?></p>
            <?php endif; ?>
            <?= $this->partial('partials/field-error', ['field' => 'cover']) ?>
            <label><span><?= $this->te('Cover size') ?></span>
                <select name="cover_size">
                    <option value="top" <?= $card->coverSize === 'top' ? 'selected' : '' ?>><?= $this->te('A strip above the title') ?></option>
                    <option value="full" <?= $card->coverSize === 'full' ? 'selected' : '' ?>><?= $this->te('Large, behind the title') ?></option>
                </select></label>

            <fieldset class="sticker-choices">
                <legend><?= $this->te('Sticker') ?></legend>
                <label class="sticker-choice"><input type="radio" name="sticker" value="" <?= $card->sticker === null ? 'checked' : '' ?>><span><?= $this->te('None') ?></span></label>
                <?php foreach (CardSummary::STICKERS as $key => [$emoji, $label]): ?>
                    <label class="sticker-choice" title="<?= $this->te($label) ?>">
                        <input type="radio" name="sticker" value="<?= $key ?>" <?= $card->sticker === $key ? 'checked' : '' ?>>
                        <span aria-hidden="true"><?= $emoji ?></span><span class="visually-hidden"><?= $this->te($label) ?></span></label>
                <?php endforeach; ?>
            </fieldset>
            <p class="muted small"><?= $this->te('Removing a sticker changes nothing else on the card.') ?></p>

            <label><span><?= $this->te('Kind of card') ?></span>
                <select name="kind" class="js-card-kind">
                    <option value="card" <?= $card->kind === 'card' ? 'selected' : '' ?>><?= $this->te('Ordinary card') ?></option>
                    <option value="separator" <?= $card->kind === 'separator' ? 'selected' : '' ?>><?= $this->te('Separator (a heading between cards)') ?></option>
                    <?php if ($linkBoards || $card->kind === 'board'): ?>
                        <option value="board" <?= $card->kind === 'board' ? 'selected' : '' ?>><?= $this->te('Link to another board') ?></option>
                    <?php endif; ?>
                </select></label>
            <?php if ($linkBoards): ?>
                <label class="js-link-board"><span><?= $this->te('Board to link to') ?></span>
                    <select name="link_board">
                        <?php foreach ($linkBoards as $other): ?>
                            <option value="<?= $this->e($other->publicId) ?>" <?= $other->id === $card->linkBoardId ? 'selected' : '' ?>><?= $this->e($other->name) ?></option>
                        <?php endforeach; ?>
                    </select></label>
            <?php endif; ?>
            <?= $this->partial('partials/field-error', ['field' => 'link_board']) ?>
            <div><button class="btn btn-primary" type="submit"><?= $this->te('Save look') ?></button></div>
        </form>
    <?php endif; ?>
</section>
