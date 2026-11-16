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
 * @var bool $canAttach may send a file with a comment
 * @var callable(\App\Modules\Discussion\Contract\Comment): bool $canModerate
 * @var array<int, \App\Modules\Attachment\Contract\Attachment> $files attachments sent with comments, by id
 * @var array{names: list<string>, reason: string}|null $unreached mentioned people the last comment could not reach
 * @var \App\Support\TimeAgo $timeAgo
 */
$cardPath = '/cards/' . $card->publicId;
$reactionKinds = \App\Modules\Discussion\Contract\Reactions::ALL;

/**
 * Safe Markdown, with the people it mentions highlighted. All spellings go
 * into one pattern, longest first, so "@Mona Hassan" is wrapped once and
 * never again as "@Mona".
 */
$render = function (\App\Modules\Discussion\Contract\Comment $comment) use ($handles): string {
    $html = $this->markdown($comment->body);
    $spellings = ['card', 'board'];
    foreach ($comment->mentions as $userId) {
        foreach ($handles[$userId] ?? [] as $spelling) {
            $spellings[] = preg_quote($this->e($spelling), '/');
        }
    }
    usort($spellings, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

    return (string) preg_replace('/@(?:' . implode('|', $spellings) . ')(?![\p{L}\p{N}_])/iu', '<span class="mention">$0</span>', $html);
};
?>
<section class="comments stack" id="comments" aria-label="<?= $this->te('Comments') ?>">
    <h2 class="section-title"><?= $this->icon('comment') ?> <?= $this->te('Comments') ?></h2>

    <?php if ($canComment): ?>
        <form action="<?= $this->url($cardPath . '/comments') ?>" method="post" class="stack comment-form" id="comment-form"
              <?= $canAttach ? 'enctype="multipart/form-data"' : '' ?>>
            <?= $this->csrfField() ?>
            <textarea name="body" rows="3" <?= $canAttach ? '' : 'required' ?> maxlength="5000" dir="auto" class="js-comment"
                      data-typing-url="<?= $this->url($cardPath . '/typing') ?>"
                      aria-label="<?= $this->te('Write a comment') ?>" aria-describedby="comment-help"
                      placeholder="<?= $this->te('Write a comment… Use @name to mention someone.') ?>"><?= $this->e($this->form->old('body')) ?></textarea>
            <p class="muted small" id="comment-help"><?= $this->te('@card tells everyone on this card, @board tells everyone on the board.') ?></p>
            <?= $this->partial('partials/field-error', ['field' => 'body']) ?>
            <div class="comment-form-actions">
                <button class="btn btn-primary" type="submit"><?= $this->te('Comment') ?></button>
                <?php if ($canAttach): ?>
                    <label class="btn btn-ghost comment-file">
                        <?= $this->icon('paperclip') ?> <span><?= $this->te('Attach a file') ?></span>
                        <input type="file" name="file" class="js-comment-file">
                    </label>
                    <span class="muted small js-comment-file-name" aria-live="polite"></span>
                <?php endif; ?>
            </div>
            <?= $this->partial('partials/field-error', ['field' => 'file']) ?>
            <label class="check small muted js-typing-share-row" hidden>
                <input type="checkbox" class="js-typing-share" checked>
                <span><?= $this->te('Let others see when I am writing') ?></span>
            </label>
        </form>
    <?php endif; ?>
    <p class="typing-now muted small js-typing" aria-live="polite" hidden
       data-url="<?= $this->url($cardPath . '/typing') ?>"
       data-one="<?= $this->te(':name is writing a comment…') ?>"
       data-many="<?= $this->te(':names are writing comments…') ?>"></p>

    <?php if ($unreached): ?>
        <div class="banner banner-warning" role="status">
            <?= $this->icon('flag') ?>
            <p><?= $this->te('Not notified: :names.', ['names' => implode('، ', $unreached['names'])]) ?>
                <?= $this->te($unreached['reason']) ?></p>
        </div>
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
                    <?php if ($comment->body !== ''): ?>
                        <div class="prose" dir="auto"><?= $render($comment) ?></div>
                    <?php endif; ?>
                    <?php $file = $comment->attachmentId !== null ? ($files[$comment->attachmentId] ?? null) : null; ?>
                    <?php if ($file): ?>
                        <a class="comment-file-chip" href="<?= $this->url('/attachments/' . $file->publicId) ?>" target="_blank" rel="noopener">
                            <?php if ($file->isImage()): ?>
                                <img src="<?= $this->url('/attachments/' . $file->publicId) ?>" alt="" loading="lazy" width="120" height="90">
                            <?php else: ?>
                                <?= $this->icon('paperclip') ?>
                            <?php endif; ?>
                            <bdi><?= $this->e($file->name) ?></bdi> <small class="muted"><?= $this->e($file->readableSize()) ?></small>
                        </a>
                    <?php elseif ($comment->attachmentId !== null || ($comment->body === '')): ?>
                        <p class="muted small"><?= $this->te('The attached file was removed.') ?></p>
                    <?php endif; ?>

                    <?php
                    $reactionButton = function (string $key, array $who, bool $inPicker) use ($comment, $viewerId, $people, $canComment, $reactionKinds): string {
                        [$emoji, $label] = $reactionKinds[$key];
                        $names = implode('، ', array_map(fn (int $id) => $people[$id]->name ?? $this->t('Someone'), $who));
                        $text = $this->t($label) . ($who ? ' · ' . count($who) . ($names !== '' ? ': ' . $names : '') : '');

                        return '<form action="' . $this->url('/comments/' . $comment->id . '/reactions') . '" method="post" class="js-react">'
                            . $this->csrfField()
                            . '<input type="hidden" name="emoji" value="' . $key . '">'
                            . '<button type="submit" class="reaction' . ($inPicker ? ' reaction-pick' : '') . '" data-emoji="' . $key . '"'
                            . ' aria-pressed="' . (in_array($viewerId, $who, true) ? 'true' : 'false') . '"' . ($canComment ? '' : ' disabled')
                            . ' title="' . $this->e($text) . '" aria-label="' . $this->e($text) . '">'
                            . '<span aria-hidden="true">' . $emoji . '</span>'
                            . ($inPicker ? '' : '<span class="reaction-count" aria-hidden="true">' . count($who) . '</span>')
                            . '</button></form>';
                    };
                    ?>
                    <?php if ($comment->reactions || $canComment): ?>
                        <div class="reactions" role="group" aria-label="<?= $this->te('Reactions') ?>">
                            <?php foreach ($reactionKinds as $key => $kind): ?>
                                <?php if (!empty($comment->reactions[$key])): ?>
                                    <?= $reactionButton($key, $comment->reactions[$key], false) ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($canComment): ?>
                                <details class="menu reaction-picker">
                                    <summary class="reaction reaction-add" title="<?= $this->te('Add a reaction') ?>" aria-label="<?= $this->te('Add a reaction') ?>">
                                        <span aria-hidden="true">☺+</span>
                                    </summary>
                                    <div class="menu-panel reaction-panel">
                                        <?php foreach ($reactionKinds as $key => $kind): ?>
                                            <?= $reactionButton($key, $comment->reactions[$key] ?? [], true) ?>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php $mine = $comment->authorId === $viewerId; ?>
                    <div class="comment-actions">
                            <button type="button" class="link-btn js-copy" data-copy="<?= $this->url($cardPath . '#comment-' . $comment->id) ?>"
                                    data-copied="<?= $this->te('Link copied') ?>"><?= $this->te('Copy link') ?></button>
                            <?php if ($mine && $canComment): ?>
                                <details class="comment-edit">
                                    <summary class="link-btn"><?= $this->te('Edit') ?></summary>
                                    <form action="<?= $this->url('/comments/' . $comment->id) ?>" method="post" class="stack">
                                        <?= $this->csrfField() ?>
                                        <textarea name="body" rows="3" <?= $comment->attachmentId !== null ? '' : 'required' ?> maxlength="5000" dir="auto" aria-label="<?= $this->te('Edit comment') ?>"><?= $this->e($comment->body) ?></textarea>
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
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($hasMore): ?>
        <a class="btn btn-ghost" href="<?= $this->url($cardPath . '?comments_before=' . end($comments)->id . '#comments') ?>"><?= $this->te('Older comments') ?></a>
    <?php endif; ?>
</section>
