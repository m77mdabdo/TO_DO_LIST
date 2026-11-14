<?php
/**
 * My API tokens: what each may do and where, and a form for a new one.
 *
 * @var \App\View\View $this
 * @var list<\App\Modules\Integration\Contract\ApiToken> $tokens
 * @var array<int, \App\Modules\Board\Contract\BoardSummary> $boards
 * @var ?string $secret a token just made, shown once
 * @var list<int> $expiry
 * @var \DateTimeImmutable $today
 */
$form = $this->form;
$now = $today->format('Y-m-d H:i:s');
?>
<?= $this->partial('partials/app-header') ?>

<main class="wrap narrow stack integrations-page">
    <section class="card stack">
        <h1 class="section-title"><?= $this->icon('key') ?> <?= $this->te('API tokens') ?></h1>
        <p class="muted"><?= $this->te('A token lets a script or another app use this site as you, through the API at /api/v1. Each token only reaches the boards you pick, can be limited to reading, and never does more than you can.') ?></p>

        <?php if ($secret !== null): ?>
            <div class="token-secret" role="status">
                <strong><?= $this->te('Your new token') ?></strong>
                <code dir="ltr" class="token-value"><?= $this->e($secret) ?></code>
                <p class="small"><?= $this->te('Copy it now and keep it somewhere safe. It will not be shown again.') ?></p>
            </div>
        <?php endif; ?>

        <?php if ($tokens === []): ?>
            <p class="empty-note"><?= $this->te('You have no tokens yet.') ?></p>
        <?php else: ?>
            <ul class="automation-list">
                <?php foreach ($tokens as $token): ?>
                    <?php $active = $token->isActive($now); ?>
                    <li class="automation-item <?= $active ? '' : 'is-paused' ?>">
                        <div class="automation-text">
                            <strong dir="auto"><?= $this->e($token->name) ?></strong>
                            <span class="pill"><?= $this->te($token->canWrite() ? 'Read and add cards' : 'Read only') ?></span>
                            <?php if ($token->revokedAt !== null): ?><span class="pill"><?= $this->te('Revoked') ?></span><?php elseif (!$active): ?><span class="pill"><?= $this->te('Expired') ?></span><?php endif; ?>
                            <p class="small" dir="auto"><?= $this->te('Boards: :boards', ['boards' => implode('، ', array_map(fn (int $id) => $boards[$id]->name ?? $this->t('(no longer available)'), $token->boardIds))]) ?></p>
                            <p class="muted small">
                                <?= $token->expiresAt !== null ? $this->te('Expires on :date', ['date' => substr($token->expiresAt, 0, 10)]) : $this->te('Never expires') ?>
                                · <?= $token->lastUsedAt !== null ? $this->te('Last used :date', ['date' => substr($token->lastUsedAt, 0, 16)]) : $this->te('Never used') ?>
                            </p>
                        </div>
                        <?php if ($token->revokedAt === null): ?>
                            <form action="<?= $this->url('/integrations/tokens/' . $token->publicId . '/revoke') ?>" method="post">
                                <?= $this->csrfField() ?>
                                <button class="btn btn-ghost btn-danger" type="submit"><?= $this->icon('trash') ?> <?= $this->te('Revoke') ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card stack" id="new">
        <h2 class="section-title"><?= $this->icon('plus') ?> <?= $this->te('New token') ?></h2>
        <form action="<?= $this->url('/integrations/tokens') ?>" method="post" class="stack" novalidate>
            <?= $this->csrfField() ?>
            <label><span><?= $this->te('Name') ?></span>
                <input name="name" maxlength="100" required dir="auto" value="<?= $this->e($form->old('name')) ?>" placeholder="<?= $this->te('e.g. Reports script') ?>"
                       aria-invalid="<?= $form->error('name') ? 'true' : 'false' ?>"></label>
            <?php if ($form->error('name')): ?><p class="field-error"><?= $this->te((string) $form->error('name')) ?></p><?php endif; ?>
            <div class="field-row">
                <label><span><?= $this->te('It may') ?></span>
                    <select name="scope">
                        <option value="read"><?= $this->te('Read only') ?></option>
                        <option value="write" <?= $form->old('scope') === 'write' ? 'selected' : '' ?>><?= $this->te('Read and add cards') ?></option>
                    </select></label>
                <label><span><?= $this->te('Expires after') ?></span>
                    <select name="days">
                        <?php foreach ($expiry as $days): ?>
                            <option value="<?= $days ?>" <?= $days === 90 ? 'selected' : '' ?>><?= $days === 0 ? $this->te('Never') : $this->te(':days days', ['days' => (string) $days]) ?></option>
                        <?php endforeach; ?>
                    </select></label>
            </div>
            <fieldset class="stack-sm">
                <legend><?= $this->te('Boards it may use') ?></legend>
                <?php foreach ($boards as $board): ?>
                    <label class="check"><input type="checkbox" name="boards[]" value="<?= $this->e($board->publicId) ?>" <?= in_array($board->publicId, $form->oldArray('boards'), true) ? 'checked' : '' ?>> <span dir="auto"><?= $this->e($board->name) ?></span></label>
                <?php endforeach; ?>
                <?php if ($form->error('boards')): ?><p class="field-error"><?= $this->te((string) $form->error('boards')) ?></p><?php endif; ?>
            </fieldset>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $this->icon('key') ?> <?= $this->te('Create token') ?></button>
            </div>
        </form>
    </section>

    <section class="card stack">
        <h2 class="section-title"><?= $this->icon('link') ?> <?= $this->te('Using the API') ?></h2>
        <pre class="code-sample" dir="ltr">curl -H "Authorization: Bearer tdb_…" <?= $this->e($this->url('/api/v1/boards')) ?>

curl -H "Authorization: Bearer tdb_…" -H "Content-Type: application/json" \
     -d '{"list": "To do", "title": "From a script", "due_date": "2026-12-01"}' \
     <?= $this->e($this->url('/api/v1/boards/BOARD_ID/cards')) ?></pre>
        <p class="muted small"><?= $this->te('Endpoints: GET /api/v1/me, GET /api/v1/boards, GET /api/v1/boards/{id}/cards, POST /api/v1/boards/{id}/cards.') ?></p>
    </section>
</main>
