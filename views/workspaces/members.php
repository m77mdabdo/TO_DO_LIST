<?php
/**
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var list<array{userId: int, role: \App\Modules\Workspace\Contract\WorkspaceRole}> $members
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 */
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap narrow">
    <section class="card stack">
        <div>
            <p class="eyebrow" dir="auto"><?= $this->e($workspace->companyName) ?></p>
            <h1><?= $this->te('Members') ?> <span class="muted">(<?= count($members) ?>)</span></h1>
        </div>

        <ul class="member-list">
            <?php foreach ($members as $member): $person = $people[$member['userId']] ?? null; ?>
                <?php if ($person === null) continue; ?>
                <li>
                    <span class="avatar"><?= $this->e($person->initial()) ?></span>
                    <span class="member-name">
                        <strong dir="auto"><?= $this->e($person->name) ?></strong>
                        <small class="muted"><?= $this->e($person->email) ?></small>
                    </span>
                    <span class="pill"><?= $this->te($member['role']->label()) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="muted small"><?= $this->te('Inviting people by email comes in the team phase.') ?></p>
    </section>
</main>
