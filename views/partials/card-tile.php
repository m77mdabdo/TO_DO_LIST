<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var list<\App\Modules\Label\Contract\Label> $labels
 * @var list<\App\Modules\Identity\Contract\User> $members
 * @var list<array{icon: string, text: string, class: string, title: string}> $badges
 * @var bool $canMove
 * @var bool $canEdit
 * @var \DateTimeImmutable $today
 * @var \App\Modules\Board\Contract\CardDisplay|null $display what this person chose to see
 * @var bool $selecting show a checkbox that belongs to the bulk form
 * @var string|null $cover path of the cover picture, when the cover is an image
 * @var list<array{field: \App\Modules\Field\Contract\CustomField, text: string}> $fields custom fields shown on the tile
 * @var \App\Modules\Board\Contract\BoardSummary|null $linkedBoard for a board-link card, the board when the viewer can open it
 */
use App\Modules\Card\Contract\CardSummary;

$display ??= null;
$selecting ??= false;
$cover ??= null;
$fields ??= [];
$linkedBoard ??= null;
$shows = static fn (string $part): bool => $display === null || $display->shows($part);
$due = $card->dueDate();
$dueClass = match (true) {
    $due === null => '',
    $card->isComplete() => 'due-done',
    $card->isOverdue($today) => 'due-overdue',
    $due === $today->format('Y-m-d') => 'due-today',
    default => '',
};
$showCover = $shows('covers') && ($card->coverColor !== null || $cover !== null);
$sticker = $card->sticker !== null ? (CardSummary::STICKERS[$card->sticker] ?? null) : null;
?>
<?php if ($card->isSeparator()): ?>
<article class="card-tile tile-separator" data-card-id="<?= $card->id ?>"
         data-move-url="<?= $this->url('/cards/' . $card->publicId . '/move') ?>"
         <?= $canMove ? 'draggable="true"' : '' ?>>
    <?php if ($selecting): ?>
        <label class="tile-select"><input type="checkbox" name="cards[]" value="<?= $this->e($card->publicId) ?>" form="bulk-form" class="js-bulk-card">
            <span class="visually-hidden"><?= $this->te('Select :name', ['name' => $card->title]) ?></span></label>
    <?php endif; ?>
    <a class="separator-title" href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"><span class="visually-hidden"><?= $this->te('Separator') ?>: </span><?= $this->e($card->title) ?></a>
</article>
<?php return; endif; ?>
<article class="card-tile <?= $card->isComplete() ? 'is-done' : '' ?> <?= $card->isTemplate ? 'is-template' : '' ?> <?= $showCover ? 'has-cover cover-' . $this->e($card->coverSize) : '' ?> <?= $showCover && $cover === null ? 'cover-color tile-' . $this->e((string) $card->coverColor) : '' ?> <?= $card->kind === 'board' ? 'is-board-link' : '' ?>"
         data-card-id="<?= $card->id ?>"
         data-move-url="<?= $this->url('/cards/' . $card->publicId . '/move') ?>"
         <?= $canMove ? 'draggable="true"' : '' ?>>
    <?php if ($showCover): ?>
        <div class="tile-cover" aria-hidden="true"><?php if ($cover !== null): ?><img src="<?= $this->url($cover) ?>" alt="" loading="lazy"><?php endif; ?></div>
    <?php endif; ?>
    <?php if ($labels && $shows('labels')): ?>
        <div class="tile-labels">
            <?php foreach ($labels as $label): ?>
                <span class="label label-<?= $this->e($label->color) ?>" dir="auto"><?= $this->e($label->name) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($selecting): ?>
        <label class="tile-select"><input type="checkbox" name="cards[]" value="<?= $this->e($card->publicId) ?>" form="bulk-form" class="js-bulk-card">
            <span class="visually-hidden"><?= $this->te('Select :name', ['name' => $card->title]) ?></span></label>
    <?php endif; ?>
    <?php if ($card->isTemplate): ?><span class="tile-template"><?= $this->icon('copy') ?><?= $this->te('Template') ?></span><?php endif; ?>
    <a class="card-title" href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"><?php if ($sticker !== null): ?><span class="sticker" title="<?= $this->te($sticker[1]) ?>" aria-label="<?= $this->te($sticker[1]) ?>"><?= $sticker[0] ?></span> <?php endif; ?><?= $this->e($card->title) ?></a>
    <?php if ($card->kind === 'board'): ?>
        <p class="tile-board-link">
            <?php if ($linkedBoard !== null): ?>
                <a href="<?= $this->url('/boards/' . $linkedBoard->publicId) ?>" dir="auto"><?= $this->icon('board') ?><?= $this->e($linkedBoard->name) ?></a>
            <?php else: ?>
                <span class="muted"><?= $this->icon('lock') ?><?= $this->te('A board you cannot open.') ?></span>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <?php if ($fields && $shows('fields')): ?>
        <ul class="tile-fields">
            <?php foreach ($fields as ['field' => $field, 'text' => $text]): ?>
                <li class="field-chip" dir="auto"><span class="field-chip-name"><?= $this->e($field->name) ?>:</span> <bdi><?= $this->e($text) ?></bdi></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="tile-foot">
        <?php if ($due !== null && $shows('dates')): ?>
            <span class="due <?= $dueClass ?>"><?= $this->icon('calendar') ?><?= $this->e($due) ?></span>
        <?php endif; ?>
        <?php foreach ($shows('badges') ? $badges : [] as $badge): ?>
            <span class="badge <?= $this->e($badge['class']) ?>" title="<?= $this->te($badge['title']) ?>"><?= $this->icon($badge['icon']) ?><?= $this->e($badge['text']) ?></span>
        <?php endforeach; ?>
        <?php if ($card->description && $shows('description')): ?>
            <span class="muted" title="<?= $this->te('Has a description') ?>"><?= $this->icon('edit') ?></span>
        <?php endif; ?>

        <?php if ($members && $shows('members')): ?>
            <span class="tile-people">
                <?php foreach (array_slice($members, 0, 3) as $member): ?>
                    <span class="avatar avatar-xs" title="<?= $this->e($member->name) ?>"><?= $this->e(mb_strtoupper(mb_substr($member->name, 0, 1))) ?></span>
                <?php endforeach; ?>
                <?php if (count($members) > 3): ?><span class="avatar avatar-xs avatar-more">+<?= count($members) - 3 ?></span><?php endif; ?>
            </span>
        <?php endif; ?>

        <?php if ($canEdit): ?>
            <form action="<?= $this->url('/cards/' . $card->publicId . '/complete') ?>" method="post" class="js-complete">
                <?= $this->csrfField() ?>
                <input type="hidden" name="done" value="<?= $card->isComplete() ? '0' : '1' ?>">
                <button class="check-btn <?= $card->isComplete() ? 'is-checked' : '' ?>" type="submit"
                        title="<?= $this->te($card->isComplete() ? 'Mark as not done' : 'Mark as done') ?>"><?= $this->icon('check') ?></button>
            </form>
        <?php endif; ?>
    </div>
</article>
