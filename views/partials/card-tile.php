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
 */
$display ??= null;
$selecting ??= false;
$shows = static fn (string $part): bool => $display === null || $display->shows($part);
$due = $card->dueDate();
$dueClass = match (true) {
    $due === null => '',
    $card->isComplete() => 'due-done',
    $card->isOverdue($today) => 'due-overdue',
    $due === $today->format('Y-m-d') => 'due-today',
    default => '',
};
?>
<article class="card-tile <?= $card->isComplete() ? 'is-done' : '' ?> <?= $card->isTemplate ? 'is-template' : '' ?>"
         data-card-id="<?= $card->id ?>"
         data-move-url="<?= $this->url('/cards/' . $card->publicId . '/move') ?>"
         <?= $canMove ? 'draggable="true"' : '' ?>>
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
    <a class="card-title" href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"><?= $this->e($card->title) ?></a>

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
