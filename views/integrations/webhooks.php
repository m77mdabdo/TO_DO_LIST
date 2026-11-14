<?php
/**
 * A board's webhooks, their recent deliveries, and a form for a new one.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var ?\App\Modules\Access\Contract\BoardRole $role
 * @var list<\App\Modules\Integration\Contract\Webhook> $webhooks
 * @var array<int, list<\App\Modules\Integration\Contract\WebhookDelivery>> $deliveries
 */
use App\Modules\Integration\Contract\Webhook;

$form = $this->form;
$chosen = $form->oldArray('events') ?: array_keys(Webhook::EVENTS);
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('boards/view-bar', ['board' => $board, 'role' => $role, 'current' => '/webhooks', 'query' => []]) ?>

<main class="wrap view-page stack integrations-page">
    <section class="card stack">
        <h2 class="section-title"><?= $this->icon('plug') ?> <?= $this->te('Webhooks') ?></h2>
        <p class="muted"><?= $this->te('A webhook sends a short JSON message to another service (Slack, a script, a calendar) when something happens on this board. Each message is signed with the webhook\'s secret in the X-Todo-Signature header (HMAC-SHA256), so the receiver can check it came from here.') ?></p>

        <?php if ($webhooks === []): ?>
            <p class="empty-note"><?= $this->te('This board has no webhooks.') ?></p>
        <?php endif; ?>
        <?php foreach ($webhooks as $hook): ?>
            <article class="automation-item webhook <?= $hook->enabled ? '' : 'is-paused' ?>">
                <div class="automation-text">
                    <strong dir="ltr" class="webhook-url"><?= $this->e($hook->url) ?></strong>
                    <?php if (!$hook->enabled): ?><span class="pill"><?= $this->te('Paused') ?></span><?php endif; ?>
                    <p class="small"><?= $this->e(implode(' · ', array_map(fn (string $e) => $this->t(Webhook::EVENTS[$e] ?? $e), $hook->events))) ?></p>
                    <details class="small">
                        <summary><?= $this->te('Show the secret') ?></summary>
                        <code dir="ltr" class="token-value"><?= $this->e($hook->secret) ?></code>
                    </details>
                    <?php if (($deliveries[$hook->id] ?? []) !== []): ?>
                        <details class="small">
                            <summary><?= $this->te('Recent deliveries') ?></summary>
                            <ul class="delivery-list">
                                <?php foreach ($deliveries[$hook->id] as $d): ?>
                                    <li class="run-<?= $d->status === 'sent' ? 'ok' : ($d->status === 'failed' ? 'failed' : 'pending') ?>">
                                        <span class="pill run-status"><?= $this->te(['sent' => 'Sent', 'failed' => 'Failed', 'pending' => 'Waiting'][$d->status] ?? $d->status) ?></span>
                                        <code dir="ltr"><?= $this->e($d->event) ?></code>
                                        <span class="muted"><?= $this->e(substr($d->createdAt, 0, 16)) ?></span>
                                        <?php if ($d->responseCode !== null): ?><span dir="ltr">HTTP <?= $d->responseCode ?></span><?php endif; ?>
                                        <?php if ($d->error !== ''): ?><span class="muted" dir="auto"><?= $this->e($this->t($d->error)) ?></span><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </div>
                <div class="automation-tools">
                    <form action="<?= $this->url('/webhooks/' . $hook->publicId . '/toggle') ?>" method="post">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="on" value="<?= $hook->enabled ? '0' : '1' ?>">
                        <button class="btn btn-ghost" type="submit"><?= $this->icon($hook->enabled ? 'pause' : 'play') ?><span><?= $this->te($hook->enabled ? 'Pause' : 'Turn on') ?></span></button>
                    </form>
                    <form action="<?= $this->url('/webhooks/' . $hook->publicId . '/delete') ?>" method="post">
                        <?= $this->csrfField() ?>
                        <button class="btn btn-ghost btn-danger" type="submit"><?= $this->icon('trash') ?><span class="visually-hidden"><?= $this->te('Delete') ?></span></button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="card stack" id="new">
        <h2 class="section-title"><?= $this->icon('plus') ?> <?= $this->te('New webhook') ?></h2>
        <form action="<?= $this->url('/boards/' . $board->publicId . '/webhooks') ?>" method="post" class="stack" novalidate>
            <?= $this->csrfField() ?>
            <label><span><?= $this->te('Address (https)') ?></span>
                <input name="url" type="url" required maxlength="2000" dir="ltr" placeholder="https://example.com/hooks/todo" value="<?= $this->e($form->old('url')) ?>"
                       aria-invalid="<?= $form->error('url') ? 'true' : 'false' ?>"></label>
            <?php if ($form->error('url')): ?><p class="field-error"><?= $this->te((string) $form->error('url')) ?></p><?php endif; ?>
            <fieldset class="stack-sm">
                <legend><?= $this->te('Send a message when') ?></legend>
                <?php foreach (Webhook::EVENTS as $event => $label): ?>
                    <label class="check"><input type="checkbox" name="events[]" value="<?= $this->e($event) ?>" <?= in_array($event, $chosen, true) ? 'checked' : '' ?>> <span><?= $this->te($label) ?></span></label>
                <?php endforeach; ?>
                <?php if ($form->error('events')): ?><p class="field-error"><?= $this->te((string) $form->error('events')) ?></p><?php endif; ?>
            </fieldset>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $this->icon('plug') ?> <?= $this->te('Add webhook') ?></button>
            </div>
        </form>
    </section>
</main>
