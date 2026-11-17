<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var list<\App\Modules\Audit\Contract\AuditEntry> $entries
 * @var bool $hasMore
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var list<int> $actorIds workspace members, for the person filter
 * @var list<string> $actions recorded actions, for the action filter
 * @var array{actor: ?int, action: ?string, from: ?string, to: ?string} $filters
 * @var array<string, string> $query the filters as query parameters
 * @var array<string, string> $groups action groups for the filter (prefix => name)
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
    'member.suspended'     => ':actor suspended :target (:role)',
    'member.reactivated'   => ':actor turned :target back on (:role)',
    'workspace.policy_changed' => ':actor changed the workspace rules',
    'workspace.ownership_transferred' => ':actor handed the workspace over to :target',
    'board.public_link_created' => ':actor made a public link for the board :target',
    'board.public_link_revoked' => ':actor turned off the public link of the board :target',
    'account.deleted'      => 'An account was deleted (:target)',
];
$ruleNames = ['members_invite' => 'Members may invite people too', 'invite_approval' => 'Approval of members\' invitations', 'public_links' => 'Public board links', 'invite_role' => 'Role given by members\' invitations'];
$qs = static fn (array $q): string => $q !== [] ? '?' . http_build_query($q) : '';
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
        <form action="<?= $this->url($base . '/audit') ?>" method="get" class="audit-filters" aria-label="<?= $this->te('Filter the audit log') ?>">
            <label><span><?= $this->te('Person') ?></span>
                <select name="actor">
                    <option value=""><?= $this->te('Anyone') ?></option>
                    <?php foreach ($actorIds as $id): if (!isset($people[$id])) continue; ?>
                        <option value="<?= $id ?>" <?= $filters['actor'] === $id ? 'selected' : '' ?>><?= $this->e($people[$id]->name) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label><span><?= $this->te('Action') ?></span>
                <select name="action">
                    <option value=""><?= $this->te('Any action') ?></option>
                    <optgroup label="<?= $this->te('Groups') ?>">
                        <?php foreach ($groups as $value => $text): ?>
                            <option value="<?= $this->e($value) ?>" <?= $filters['action'] === $value ? 'selected' : '' ?>><?= $this->te($text) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="<?= $this->te('Single actions') ?>">
                        <?php foreach ($actions as $action): ?>
                            <option value="<?= $this->e($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?> dir="ltr"><?= $this->e($action) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select></label>
            <label><span><?= $this->te('From') ?></span><input type="date" name="from" value="<?= $this->e((string) $filters['from']) ?>"></label>
            <label><span><?= $this->te('To') ?></span><input type="date" name="to" value="<?= $this->e((string) $filters['to']) ?>"></label>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $this->te('Filter') ?></button>
                <?php if ($query !== []): ?><a class="btn btn-ghost" href="<?= $this->url($base . '/audit') ?>"><?= $this->te('Clear') ?></a><?php endif; ?>
                <a class="btn btn-ghost" href="<?= $this->url($base . '/audit.csv' . $qs($query)) ?>"><?= $this->icon('download') ?><?= $this->te('Download CSV') ?></a>
            </div>
        </form>
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
                        <?php if ($entry->action === 'workspace.policy_changed' && $entry->details !== []): ?>
                            <ul class="plain-list small">
                                <?php foreach ($entry->details as $key => $value): ?>
                                    <li><?= $this->te($ruleNames[$key] ?? $key) ?>: <?= $this->te($value === 'on' ? 'On' : ($value === 'off' ? 'Off' : $value)) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <small class="muted"><?= $this->e($entry->createdAt) ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($hasMore): ?>
            <a class="btn btn-ghost" href="<?= $this->url($base . '/audit' . $qs($query + ['before' => (string) end($entries)->id])) ?>"><?= $this->te('Older entries') ?></a>
        <?php endif; ?>
    </section>
</main>
