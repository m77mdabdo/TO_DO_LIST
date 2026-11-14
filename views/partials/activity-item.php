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
    'board.participation' => ':actor changed who can comment and vote',
    'board.recolored'   => ':actor changed the board color',
    'board.described'   => ':actor updated the board description',
    'board.made_template'   => ':actor made this board a template',
    'board.unmade_template' => ':actor stopped using this board as a template',
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
    'card.start_changed' => ':actor changed the start date of :subject',
    'card.member_added' => ':actor assigned :member to :subject',
    'card.member_removed' => ':actor took :member off :subject',
    'card.completed'    => ':actor completed :subject',
    'card.reopened'     => ':actor reopened :subject',
    'card.moved'        => ':actor moved :subject from :from to :to',
    'card.archived'     => ':actor archived :subject',
    'card.restored'     => ':actor restored :subject',
    'card.deleted'      => ':actor deleted :subject',
    'checklist.added'   => ':actor added the checklist :subject to :card',
    'checklist.renamed' => ':actor renamed a checklist on :card to :subject',
    'checklist.removed' => ':actor removed the checklist :subject from :card',
    'checklist.item_completed' => ':actor completed :subject on :card',
    'checklist.item_reopened'  => ':actor reopened :subject on :card',
    'checklist.item_assigned'  => ':actor gave :subject on :card to :member',
    'attachment.added'   => ':actor attached :subject to :card',
    'attachment.removed' => ':actor removed the attachment :subject from :card',
    'comment.posted'    => ':actor commented on :subject',
    'vote.added'        => ':actor voted for :subject',
    'label.attached'    => ':actor added the label :subject',
    'label.detached'    => ':actor removed the label :subject',
    'board.member_added'   => ':actor added :member to the board as :subject',
    'board.member_role'    => ':actor made :member a board :subject',
    'board.member_removed' => ':actor took :member off the board',
];
// Translate the sentence first, then drop the names in as isolated, bold
// spans, so an English title inside an Arabic sentence keeps its place.
$name = fn (string $value): string => '<bdi class="activity-name">' . $this->e($value) . '</bdi>';
$html = strtr($this->e($this->t($templates[$entry->type] ?? ':actor updated :subject')), [
    ':actor'   => $name($actor),
    // For board roles the subject is a role name, which is translated too.
    ':subject' => $name(str_starts_with($entry->type, 'board.member_') ? $this->t($entry->subject) : $entry->subject),
    ':list'    => $name((string) ($entry->data['list'] ?? '')),
    ':from'    => $name((string) ($entry->data['from'] ?? '')),
    ':to'      => $name((string) ($entry->data['to'] ?? '')),
    ':member'  => $name((string) ($entry->data['member'] ?? '')),
    ':card'    => $name((string) ($entry->data['card'] ?? '')),
]);
?>
<li class="activity-item">
    <span class="avatar avatar-sm"><?= $this->e(mb_strtoupper(mb_substr($actor, 0, 1))) ?></span>
    <span>
        <?= $html ?>
        <small class="muted"><?= $this->e($timeAgo->format(new DateTimeImmutable($entry->createdAt))) ?></small>
    </span>
</li>
