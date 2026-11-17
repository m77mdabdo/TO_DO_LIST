<?php
/**
 * The workspace's integration center.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Workspace\Contract\WorkspaceSummary $workspace
 * @var bool $canManage
 * @var list<\App\Modules\Integration\Contract\IntegrationType> $types
 * @var list<array<string, mixed>> $connected
 * @var array<int, list<array{event: string, status: string, error: string, responseCode: ?int, createdAt: string}>> $deliveries
 * @var list<\App\Modules\Board\Contract\BoardSummary> $boards boards the viewer can open
 * @var list<array{action: string, details: string, actorId: ?int, createdAt: string, integration: string}> $log
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var bool $ready APP_KEY is set
 */
$form = $this->form;
$typeOf = [];
foreach ($types as $type) {
    $typeOf[$type->key()] = $type;
}
$actions = [
    'connected' => 'Connected', 'settings_changed' => 'Settings changed', 'rotated' => 'Secret rotated', 'paused' => 'Paused',
    'resumed' => 'Turned on again', 'disconnected' => 'Disconnected', 'tested' => 'Test message',
];
$statusText = ['sent' => 'Sent', 'failed' => 'Failed', 'pending' => 'Waiting'];
?>
<?= $this->partial('partials/app-header') ?>
<main class="wrap stack integrations-page">
    <h1><?= $this->icon('plug') ?> <?= $this->te('Integrations') ?> <small class="muted" dir="auto"><?= $this->e($workspace->name) ?></small></h1>
    <p class="muted"><?= $this->te('Ready-made connections to other tools for this whole workspace. For scripts of your own, use') ?> <a href="<?= $this->url('/integrations') ?>"><?= $this->te('API tokens') ?></a> <?= $this->te('or a board\'s webhooks.') ?></p>

    <?php if (!$canManage): ?>
        <p class="empty-note"><?= $this->te('Only the workspace owner and admins can see and change integrations.') ?></p>
    <?php else: ?>
        <?php if (!$ready): ?>
            <div class="alert" role="alert"><?= $this->icon('flag') ?><?= $this->te('APP_KEY is not set in .env, so secrets can\'t be stored encrypted. Run php bin/app-key.php, put the line it prints in .env, and reload.') ?></div>
        <?php endif; ?>

        <?php foreach ($connected as $item): ?>
            <?php $type = $typeOf[$item['type']] ?? null; $chosen = array_flip($item['boardIds']); $events = array_flip($item['events']); ?>
            <section class="card stack integration <?= $item['status'] !== 'active' ? 'is-paused' : '' ?>" id="integration-<?= $this->e($item['publicId']) ?>" aria-labelledby="int-<?= $this->e($item['publicId']) ?>">
                <h2 id="int-<?= $this->e($item['publicId']) ?>" class="section-title">
                    <?= $this->icon('plug') ?> <span dir="auto"><?= $this->e($item['name']) ?></span>
                    <span class="pill"><?= $this->e($type?->name() ?? $item['type']) ?></span>
                    <span class="pill run-<?= $item['status'] === 'active' ? 'ok' : 'pending' ?>"><?= $this->te($item['status'] === 'active' ? 'On' : 'Paused') ?></span>
                </h2>
                <p class="small muted">
                    <?= $this->te('Connected by :name on :date.', ['name' => $people[$item['connectedBy'] ?? 0]->name ?? $this->t('Someone'), 'date' => substr($item['createdAt'], 0, 10)]) ?>
                    <?= $this->te('Secret:') ?> <code dir="ltr"><?= $this->e($item['secretHint']) ?></code>.
                    <?php if ($item['lastSuccessAt'] !== null): ?><?= $this->te('Last message sent :date.', ['date' => substr((string) $item['lastSuccessAt'], 0, 16)]) ?><?php else: ?><?= $this->te('Nothing sent yet.') ?><?php endif; ?>
                </p>
                <?php if ($item['lastError'] !== ''): ?>
                    <p class="small field-error" role="status"><?= $this->te('Last problem (:date):', ['date' => substr((string) $item['lastErrorAt'], 0, 16)]) ?> <?= $this->te($item['lastError']) ?></p>
                <?php endif; ?>
                <?= $this->partial('partials/field-error', ['field' => 'test_' . $item['publicId']]) ?>

                <form action="<?= $this->url('/integrations/' . $item['publicId']) ?>" method="post" class="stack" novalidate>
                    <?= $this->csrfField() ?>
                    <label><span><?= $this->te('Name') ?></span><input name="name" maxlength="100" dir="auto" value="<?= $this->e($item['name']) ?>"></label>
                    <fieldset class="check-grid">
                        <legend><?= $this->te('Boards') ?></legend>
                        <?php foreach ($boards as $b): ?>
                            <label class="check"><input type="checkbox" name="boards[]" value="<?= $this->e($b->publicId) ?>" <?= isset($chosen[$b->id]) ? 'checked' : '' ?>> <span dir="auto"><?= $this->e($b->name) ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <fieldset class="check-grid">
                        <legend><?= $this->te('Send a message when') ?></legend>
                        <?php foreach ($type?->events() ?? [] as $key => $label): ?>
                            <label class="check"><input type="checkbox" name="events[]" value="<?= $this->e($key) ?>" <?= isset($events[$key]) ? 'checked' : '' ?>> <?= $this->te($label) ?></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <label class="check"><input type="checkbox" name="active" value="1" <?= $item['status'] === 'active' ? 'checked' : '' ?>> <?= $this->te('On (untick to pause without losing the settings)') ?></label>
                    <div><button class="btn btn-primary" type="submit"><?= $this->te('Save') ?></button></div>
                </form>

                <div class="integration-tools">
                    <form action="<?= $this->url('/integrations/' . $item['publicId'] . '/test') ?>" method="post">
                        <?= $this->csrfField() ?>
                        <button class="btn btn-ghost" type="submit"><?= $this->icon('play') ?><?= $this->te('Send a test message') ?></button>
                    </form>
                    <details class="menu">
                        <summary class="btn btn-ghost"><?= $this->icon('key') ?><?= $this->te('Rotate the secret') ?></summary>
                        <div class="menu-panel menu-wide">
                            <form action="<?= $this->url('/integrations/' . $item['publicId'] . '/rotate') ?>" method="post" class="stack" novalidate>
                                <?= $this->csrfField() ?>
                                <label><span><?= $this->te($type?->secretLabel() ?? 'Secret') ?></span>
                                    <input name="secret" type="password" autocomplete="off" dir="ltr" required></label>
                                <p class="small muted"><?= $this->te('Make a new one first (in Slack: a new incoming webhook), paste it here, then remove the old one there.') ?></p>
                                <button class="btn btn-primary" type="submit"><?= $this->te('Save the new secret') ?></button>
                            </form>
                        </div>
                    </details>
                    <form action="<?= $this->url('/integrations/' . $item['publicId'] . '/disconnect') ?>" method="post">
                        <?= $this->csrfField() ?>
                        <button class="btn btn-danger-ghost" type="submit"><?= $this->icon('close') ?><?= $this->te('Disconnect') ?></button>
                    </form>
                </div>

                <?php if (($deliveries[$item['id']] ?? []) !== []): ?>
                    <details class="small">
                        <summary><?= $this->te('Recent messages') ?></summary>
                        <ul class="delivery-list">
                            <?php foreach ($deliveries[$item['id']] as $d): ?>
                                <li class="run-<?= $d['status'] === 'sent' ? 'ok' : ($d['status'] === 'failed' ? 'failed' : 'pending') ?>">
                                    <span class="pill run-status"><?= $this->te($statusText[$d['status']] ?? $d['status']) ?></span>
                                    <code dir="ltr"><?= $this->e($d['event']) ?></code>
                                    <span class="muted"><?= $this->e(substr($d['createdAt'], 0, 16)) ?></span>
                                    <?php if ($d['error'] !== ''): ?><span class="muted" dir="auto"><?= $this->e($this->t($d['error'])) ?></span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php foreach ($types as $type): ?>
            <section class="card stack" id="connect" aria-labelledby="connect-<?= $this->e($type->key()) ?>">
                <h2 id="connect-<?= $this->e($type->key()) ?>" class="section-title"><?= $this->icon('plus') ?> <?= $this->te('Connect :name', ['name' => $type->name()]) ?></h2>
                <p><?= $this->te($type->description()) ?></p>
                <ul class="small">
                    <?php foreach ($type->permissions() as $line): ?><li><?= $this->te($line) ?></li><?php endforeach; ?>
                </ul>
                <details class="small">
                    <summary><?= $this->te('How to get the address') ?></summary>
                    <ol><?php foreach ($type->setupSteps() as $step): ?><li><?= $this->te($step) ?></li><?php endforeach; ?></ol>
                </details>
                <form action="<?= $this->url('/workspaces/' . $workspace->publicId . '/integrations') ?>" method="post" class="stack" novalidate>
                    <?= $this->csrfField() ?>
                    <input type="hidden" name="type" value="<?= $this->e($type->key()) ?>">
                    <label><span><?= $this->te('Name') ?></span>
                        <input name="name" maxlength="100" dir="auto" value="<?= $this->e($form->old('name')) ?>" placeholder="<?= $this->te('e.g. #team channel') ?>"></label>
                    <label><span><?= $this->te($type->secretLabel()) ?></span>
                        <input name="secret" type="password" autocomplete="off" dir="ltr" required placeholder="https://hooks.slack.com/services/…"
                               <?= $form->error('secret') ? 'aria-invalid="true" aria-describedby="secret-error"' : '' ?>></label>
                    <?= $this->partial('partials/field-error', ['field' => 'secret']) ?>
                    <p class="small muted"><?= $this->te('It is stored encrypted and never shown again; only its last characters are.') ?></p>
                    <fieldset class="check-grid">
                        <legend><?= $this->te('Boards') ?></legend>
                        <?php foreach ($boards as $b): ?>
                            <label class="check"><input type="checkbox" name="boards[]" value="<?= $this->e($b->publicId) ?>" <?= in_array($b->publicId, $form->oldArray('boards'), true) ? 'checked' : '' ?>> <span dir="auto"><?= $this->e($b->name) ?></span></label>
                        <?php endforeach; ?>
                        <?php if ($boards === []): ?><p class="small muted"><?= $this->te('No boards yet.') ?></p><?php endif; ?>
                    </fieldset>
                    <?= $this->partial('partials/field-error', ['field' => 'boards']) ?>
                    <fieldset class="check-grid">
                        <legend><?= $this->te('Send a message when') ?></legend>
                        <?php $old = $form->oldArray('events') ?: array_keys($type->events()); ?>
                        <?php foreach ($type->events() as $key => $label): ?>
                            <label class="check"><input type="checkbox" name="events[]" value="<?= $this->e($key) ?>" <?= in_array($key, $old, true) ? 'checked' : '' ?>> <?= $this->te($label) ?></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?= $this->partial('partials/field-error', ['field' => 'events']) ?>
                    <?= $this->partial('partials/field-error', ['field' => 'type']) ?>
                    <div><button class="btn btn-primary" type="submit" <?= $ready ? '' : 'disabled' ?>><?= $this->icon('plug') ?><?= $this->te('Connect') ?></button></div>
                </form>
            </section>
        <?php endforeach; ?>

        <section class="card stack" aria-labelledby="log-title">
            <h2 id="log-title" class="section-title"><?= $this->icon('activity') ?> <?= $this->te('Change log') ?></h2>
            <?php if ($log === []): ?>
                <p class="muted small"><?= $this->te('No changes yet.') ?></p>
            <?php else: ?>
                <ul class="plain-list small">
                    <?php foreach ($log as $entry): ?>
                        <li>
                            <span class="muted" dir="ltr"><?= $this->e(substr($entry['createdAt'], 0, 16)) ?></span>
                            <strong dir="auto"><?= $this->e($people[$entry['actorId'] ?? 0]->name ?? $this->t('Someone')) ?></strong>
                            · <?= $this->te($actions[$entry['action']] ?? $entry['action']) ?>
                            · <span dir="auto"><?= $this->e($entry['integration']) ?></span>
                            <?php if ($entry['details'] !== '' && $entry['details'] !== 'ok'): ?><span class="muted" dir="auto">(<?= $this->e($this->t($entry['details'])) ?>)</span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
