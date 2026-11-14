<?php
/**
 * Files and links on the card page (slot "card.main", filled by the Attachment module).
 *
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var list<\App\Modules\Attachment\Contract\Attachment> $attachments
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var bool $canAdd
 * @var callable(\App\Modules\Attachment\Contract\Attachment): bool $canRemove
 * @var string $accept
 * @var \App\Support\TimeAgo $timeAgo
 */
if (!$attachments && !$canAdd) {
    return;
}
$cardPath = '/cards/' . $card->publicId;
?>
<section class="card stack attachments" id="attachments" aria-label="<?= $this->te('Attachments') ?>">
    <h2 class="section-title"><?= $this->icon('paperclip') ?> <?= $this->te('Attachments') ?> <?php if ($attachments): ?><span class="count"><?= count($attachments) ?></span><?php endif; ?></h2>

    <?php if ($attachments): ?>
        <ul class="attachment-list">
            <?php foreach ($attachments as $attachment): ?>
                <?php $href = $attachment->isLink() ? (string) $attachment->url : $this->url('/attachments/' . $attachment->publicId); ?>
                <li>
                    <?php if ($attachment->isImage()): ?>
                        <a class="attachment-thumb" href="<?= $this->e($href) ?>" target="_blank" rel="noopener"><img src="<?= $this->e($href) ?>" alt="" loading="lazy" width="72" height="54"></a>
                    <?php else: ?>
                        <span class="attachment-thumb attachment-icon" aria-hidden="true"><?= $this->icon($attachment->isLink() ? 'link' : 'paperclip') ?></span>
                    <?php endif; ?>
                    <div class="attachment-meta">
                        <a href="<?= $this->e($href) ?>" <?= $attachment->isLink() ? 'target="_blank" rel="noopener noreferrer nofollow"' : ($attachment->isImage() ? 'target="_blank" rel="noopener"' : '') ?> dir="auto"><bdi><?= $this->e($attachment->name) ?></bdi></a>
                        <span class="muted small">
                            <?php if ($attachment->isLink()): ?><bdi dir="ltr"><?= $this->e((string) parse_url((string) $attachment->url, PHP_URL_HOST)) ?></bdi><?php else: ?><bdi dir="ltr"><?= $this->e($attachment->readableSize()) ?></bdi><?php endif; ?>
                            · <bdi><?= $this->e($people[$attachment->uploaderId]->name ?? $this->t('Someone')) ?></bdi>
                            · <?= $this->e($timeAgo->format(new DateTimeImmutable($attachment->createdAt))) ?>
                        </span>
                    </div>
                    <div class="row-actions">
                        <?php if (!$attachment->isLink()): ?>
                            <a class="icon-btn" href="<?= $this->url('/attachments/' . $attachment->publicId . '?download=1') ?>" title="<?= $this->te('Download') ?>" aria-label="<?= $this->te('Download') ?>"><?= $this->icon('download') ?></a>
                        <?php endif; ?>
                        <?php if ($canRemove($attachment)): ?>
                            <form action="<?= $this->url('/attachments/' . $attachment->publicId . '/delete') ?>" method="post" data-confirm="<?= $this->te('Remove this attachment?') ?>">
                                <?= $this->csrfField() ?>
                                <button class="icon-btn danger" type="submit" title="<?= $this->te('Remove') ?>" aria-label="<?= $this->te('Remove') ?>"><?= $this->icon('close') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($canAdd): ?>
        <div class="attachment-forms">
            <form action="<?= $this->url($cardPath . '/attachments') ?>" method="post" enctype="multipart/form-data" class="stack-sm">
                <?= $this->csrfField() ?>
                <label for="attachment-file"><?= $this->te('Attach a file') ?> <span class="muted small"><?= $this->te('(up to 10 MB)') ?></span></label>
                <input type="file" id="attachment-file" name="file" required accept="<?= $this->e($accept) ?>">
                <?= $this->partial('partials/field-error', ['field' => 'file']) ?>
                <div><button class="btn btn-ghost" type="submit"><?= $this->icon('paperclip') ?><?= $this->te('Upload') ?></button></div>
            </form>
            <form action="<?= $this->url($cardPath . '/links') ?>" method="post" class="stack-sm">
                <?= $this->csrfField() ?>
                <label for="attachment-url"><?= $this->te('Add a link') ?></label>
                <input type="url" id="attachment-url" name="url" required maxlength="2000" dir="ltr" placeholder="https://" value="<?= $this->e($this->form->old('url')) ?>">
                <?= $this->partial('partials/field-error', ['field' => 'url']) ?>
                <input type="text" name="title" maxlength="150" dir="auto" placeholder="<?= $this->te('Title (optional)') ?>" aria-label="<?= $this->te('Title (optional)') ?>" value="<?= $this->e($this->form->old('title')) ?>">
                <div><button class="btn btn-ghost" type="submit"><?= $this->icon('link') ?><?= $this->te('Add link') ?></button></div>
            </form>
        </div>
    <?php endif; ?>
</section>
