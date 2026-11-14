<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var list<\App\Modules\Audit\Contract\AuditEntry> $entries
 * @var bool $hasMore
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 */
$sentences = [
    'invitation.sent'      => ':actor invited :target as :role',
    'invitation.resent'    => ':actor sent the invitation to :target again',
    'invitation.revoked'   => ':actor cancelled the invitation to :target',
    'invitation.accepted'  => ':target accepted the invitation as :role',
    'member.joined'        => ':target joined as :role',
    'member.role_changed'  => ':actor changed :target from :from to :to',
    'member.removed'       => ':actor removed :target (:role)',
    'member.left'          => ':target left the workspace',
    'board.member_changed' => ':actor changed :target on the board :board from :from to :to',
];
$base = '/workspaces/' . $workspace->publicId;
$name = fn (string $value): string => '<bdi class="activity-name">' . $this->e($value) . '</bdi>';
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow">
    <a class="back-link" href="<?= $this->url($base . '/members') ?>"><?= $this->icon('left') ?> <?= $this->te('Members') ?></a>
    <section class="card stack">
        <div>
            <p class="eyebrow" dir="auto"><?= $this->e($workspace->companyName) ?></p>
            <h1><?= $this->icon('shield') ?> <?= $this->te('Audit log') ?></h1>
            <p class="muted small"><?= $this->te('Invitations, role changes and removals in this workspace. Entries can not be edited or deleted.') ?></p>
        </div>
        <?php if (!$entries): ?>
            <p class="muted"><?= $this->te('Nothing recorded yet.') ?></p>
        <?php endif; ?>
        <ul class="activity-list">
            <?php foreach ($entries as $entry): ?>
                <?php
                $replace = [':actor' => $name($entry->actorId !== null ? ($people[$entry->actorId]->name ?? $this->t('Someone')) : $this->t('Someone')), ':target' => $name($entry->target)];
                foreach ($entry->details as $key => $value) {
                    $replace[':' . $key] = $name($key === 'board' ? $value : $this->t($value));
                }
                ?>
                <li class="activity-item">
                    <div>
                        <p><?= strtr($this->e($this->t($sentences[$entry->action] ?? ':actor changed :target')), $replace) ?></p>
                        <small class="muted"><?= $this->e($entry->createdAt) ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($hasMore): ?>
            <a class="btn btn-ghost" href="<?= $this->url($base . '/audit?before=' . end($entries)->id) ?>"><?= $this->te('Older entries') ?></a>
        <?php endif; ?>
    </section>
</main>
