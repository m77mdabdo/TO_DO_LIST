<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Card\Contract\CardSummary $card
 * @var list<\App\Modules\Label\Contract\Label> $labels
 * @var bool $canMove
 * @var bool $canEdit
 * @var \DateTimeImmutable $today
 */
$due = $card->dueDate();
$dueClass = match (true) {
    $due === null => '',
    $card->isComplete() => 'due-done',
    $card->isOverdue($today) => 'due-overdue',
    $due === $today->format('Y-m-d') => 'due-today',
    default => '',
};
?>
<article class="card-tile <?= $card->isComplete() ? 'is-done' : '' ?>"
         data-card-id="<?= $card->id ?>"
         data-move-url="<?= $this->url('/cards/' . $card->publicId . '/move') ?>"
         <?= $canMove ? 'draggable="true"' : '' ?>>
    <?php if ($labels): ?>
        <div class="tile-labels">
            <?php foreach ($labels as $label): ?>
                <span class="label label-<?= $this->e($label->color) ?>" dir="auto"><?= $this->e($label->name) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <a class="card-title" href="<?= $this->url('/cards/' . $card->publicId) ?>" dir="auto"><?= $this->e($card->title) ?></a>

    <div class="tile-foot">
        <?php if ($due !== null): ?>
            <span class="due <?= $dueClass ?>"><?= $this->icon('calendar') ?><?= $this->e($due) ?></span>
        <?php endif; ?>
        <?php if ($card->description): ?>
            <span class="muted" title="<?= $this->te('Has a description') ?>"><?= $this->icon('edit') ?></span>
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
