<?php
/**
 * One line of activity, e.g. "Sara moved Design from To do to Doing".
 *
 * @var \App\View\View $this
 * @var \App\Modules\Activity\Contract\ActivityEntry $entry
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var \App\Support\TimeAgo $timeAgo
 */
$actor = $people[$entry->actorId]->name ?? $this->t('Someone');
$templates = [
    'board.created'     => ':actor created this board',
    'board.renamed'     => ':actor renamed the board to :subject',
    'board.visibility'  => ':actor changed who can see the board',
    'board.recolored'   => ':actor changed the board color',
    'board.described'   => ':actor updated the board description',
    'board.archived'    => ':actor archived the board',
    'board.restored'    => ':actor restored the board',
    'list.created'      => ':actor added the list :subject',
    'list.renamed'      => ':actor renamed a list to :subject',
    'list.moved'        => ':actor moved the list :subject',
    'list.archived'     => ':actor archived the list :subject',
    'list.restored'     => ':actor restored the list :subject',
    'card.created'      => ':actor added :subject to :list',
    'card.renamed'      => ':actor renamed :subject',
    'card.described'    => ':actor updated the description of :subject',
    'card.due_changed'  => ':actor changed the due date of :subject',
    'card.completed'    => ':actor completed :subject',
    'card.reopened'     => ':actor reopened :subject',
    'card.moved'        => ':actor moved :subject from :from to :to',
    'card.archived'     => ':actor archived :subject',
    'card.restored'     => ':actor restored :subject',
    'card.deleted'      => ':actor deleted :subject',
    'label.attached'    => ':actor added the label :subject',
    'label.detached'    => ':actor removed the label :subject',
];
$text = $this->t($templates[$entry->type] ?? ':actor updated :subject', [
    'actor'   => "\u{2068}" . $actor . "\u{2069}",
    'subject' => "\u{2068}" . $entry->subject . "\u{2069}",
    'list'    => "\u{2068}" . (string) ($entry->data['list'] ?? '') . "\u{2069}",
    'from'    => "\u{2068}" . (string) ($entry->data['from'] ?? '') . "\u{2069}",
    'to'      => "\u{2068}" . (string) ($entry->data['to'] ?? '') . "\u{2069}",
]);
?>
<li class="activity-item">
    <span class="avatar avatar-sm"><?= $this->e(mb_strtoupper(mb_substr($actor, 0, 1))) ?></span>
    <span>
        <?= $this->e($text) ?>
        <small class="muted"><?= $this->e($timeAgo->format(new DateTimeImmutable($entry->createdAt))) ?></small>
    </span>
</li>
