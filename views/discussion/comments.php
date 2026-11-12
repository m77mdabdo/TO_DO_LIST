<?php
/**
 * Comments on the card page (slot "card.main", filled by the Discussion module).
 *
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var list<\App\Modules\Discussion\Contract\Comment> $comments newest first
 * @var bool $hasMore
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var array<int, list<string>> $handles user id => spellings that mention them
 * @var int $viewerId
 * @var bool $canComment
 * @var callable(\App\Modules\Discussion\Contract\Comment): bool $canModerate
 * @var \App\Support\TimeAgo $timeAgo
 */
$cardPath = '/cards/' . $card->publicId;

/**
 * Safe Markdown, with the people it mentions highlighted. All spellings go
 * into one pattern, longest first, so "@Mona Hassan" is wrapped once and
 * never again as "@Mona".
 */
$render = function (\App\Modules\Discussion\Contract\Comment $comment) use ($handles): string {
    $html = $this->markdown($comment->body);
    $spellings = [];
    foreach ($comment->mentions as $userId) {
        foreach ($handles[$userId] ?? [] as $spelling) {
            $spellings[] = preg_quote($this->e($spelling), '/');
        }
    }
    if (!$spellings) {
        return $html;
    }
    usort($spellings, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

    return (string) preg_replace('/@(?:' . implode('|', $spellings) . ')(?![\p{L}\p{N}_])/iu', '<span class="mention">$0</span>', $html);
};
?>
<section class="comments stack" id="comments" aria-label="<?= $this->te('Comments') ?>">
    <h2 class="section-title"><?= $this->icon('comment') ?> <?= $this->te('Comments') ?></h2>

    <?php if ($canComment): ?>
        <form action="<?= $this->url($cardPath . '/comments') ?>" method="post" class="stack comment-form">
            <?= $this->csrfField() ?>
            <textarea name="body" rows="3" required maxlength="5000" dir="auto" class="js-comment"
                      aria-label="<?= $this->te('Write a comment') ?>"
                      placeholder="<?= $this->te('Write a comment… Use @name to mention someone.') ?>"><?= $this->e($this->form->old('body')) ?></textarea>
            <?= $this->partial('partials/field-error', ['field' => 'body']) ?>
            <div><button class="btn btn-primary" type="submit"><?= $this->te('Comment') ?></button></div>
        </form>
    <?php endif; ?>

    <?php if (!$comments): ?>
        <p class="muted small"><?= $this->te('No comments yet.') ?></p>
    <?php endif; ?>

    <ul class="comment-list">
        <?php foreach ($comments as $comment): ?>
            <?php $author = $people[$comment->authorId] ?? null; $name = $author?->name ?? $this->t('Someone'); ?>
            <li class="comment" id="comment-<?= $comment->id ?>">
                <span class="avatar avatar-sm" aria-hidden="true"><?= $this->e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
                <div class="comment-body">
                    <p class="comment-meta">
                        <strong><bdi><?= $this->e($name) ?></bdi></strong>
                        <small class="muted"><?= $this->e($timeAgo->format(new DateTimeImmutable($comment->createdAt))) ?><?php if ($comment->editedAt): ?> · <?= $this->te('edited') ?><?php endif; ?></small>
                    </p>
                    <div class="prose" dir="auto"><?= $render($comment) ?></div>

                    <?php $mine = $comment->authorId === $viewerId; ?>
                    <?php if (($mine && $canComment) || $canModerate($comment)): ?>
                        <div class="comment-actions">
                            <?php if ($mine && $canComment): ?>
                                <details class="comment-edit">
                                    <summary class="link-btn"><?= $this->te('Edit') ?></summary>
                                    <form action="<?= $this->url('/comments/' . $comment->id) ?>" method="post" class="stack">
                                        <?= $this->csrfField() ?>
                                        <textarea name="body" rows="3" required maxlength="5000" dir="auto" aria-label="<?= $this->te('Edit comment') ?>"><?= $this->e($comment->body) ?></textarea>
                                        <div><button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button></div>
                                    </form>
                                </details>
                            <?php endif; ?>
                            <?php if ($canModerate($comment)): ?>
                                <form action="<?= $this->url('/comments/' . $comment->id . '/delete') ?>" method="post" data-confirm="<?= $this->te('Delete this comment?') ?>">
                                    <?= $this->csrfField() ?>
                                    <button class="link-btn danger" type="submit"><?= $this->te('Delete') ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($hasMore): ?>
        <a class="btn btn-ghost" href="<?= $this->url($cardPath . '?comments_before=' . end($comments)->id . '#comments') ?>"><?= $this->te('Older comments') ?></a>
    <?php endif; ?>
</section>
