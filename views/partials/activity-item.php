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
// Translate the sentence first, then drop the names in as isolated, bold
// spans, so an English title inside an Arabic sentence keeps its place.
$name = fn (string $value): string => '<bdi class="activity-name">' . $this->e($value) . '</bdi>';
$html = strtr($this->e($this->t($templates[$entry->type] ?? ':actor updated :subject')), [
    ':actor'   => $name($actor),
    ':subject' => $name($entry->subject),
    ':list'    => $name((string) ($entry->data['list'] ?? '')),
    ':from'    => $name((string) ($entry->data['from'] ?? '')),
    ':to'      => $name((string) ($entry->data['to'] ?? '')),
]);
?>
<li class="activity-item">
    <span class="avatar avatar-sm"><?= $this->e(mb_strtoupper(mb_substr($actor, 0, 1))) ?></span>
    <span>
        <?= $html ?>
        <small class="muted"><?= $this->e($timeAgo->format(new DateTimeImmutable($entry->createdAt))) ?></small>
    </span>
</li>
