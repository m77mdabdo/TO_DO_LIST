<?php
/**
 * A board's automations: what is set up, a form to add one, and the run log.
 *
 * @var \App\View\View $this
 * @var \App\Modules\Board\Contract\BoardSummary $board
 * @var \App\Modules\Access\Contract\BoardRole $role
 * @var list<\App\Modules\Automation\Contract\Automation> $automations
 * @var array<int, string> $described automation id => sentence
 * @var list<\App\Modules\Automation\Contract\AutomationRun> $runs
 * @var array<int, string> $cardTitles
 * @var array<int, \App\Modules\Identity\Contract\User> $people
 * @var array{triggers: array<string, string>, conditions: array<string, string>, actions: array<string, string>, textActions: list<string>, lists: array<int, string>} $options
 * @var string $kind the kind the form is for
 * @var array<int, \App\Modules\Automation\Contract\AutomationRun> $failures automation id => its latest failed run
 * @var \App\Support\TimeAgo $timeAgo
 */
$form = $this->form;
$kinds = [
    'rule'         => ['bolt', 'Rules', 'Run by themselves when something happens on a card.'],
    'card_button'  => ['play', 'Card buttons', 'A button on every card that does several things at once.'],
    'board_button' => ['board', 'Board buttons', 'A button in the board bar that works on many cards.'],
    'schedule'     => ['clock', 'Schedules', 'Run every day, week or month.'],
    'due'          => ['calendar', 'Due dates', 'Run before, on or after the day a card is due.'],
];
$byKind = [];
foreach ($automations as $automation) {
    $byKind[$automation->kind][] = $automation;
}
$failures ??= [];
$detail = function (string $json): string {
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['text'])) {
        return $json;
    }
    $values = array_map(fn ($v) => is_string($v) ? $this->t($v) : (string) $v, (array) ($data['values'] ?? []));

    return $this->t((string) $data['text'], $values);
};
$base = '/boards/' . $board->publicId . '/automation';
?>
<?= $this->partial('partials/app-header') ?>
<?= $this->partial('boards/view-bar', ['board' => $board, 'role' => $role, 'current' => '/automation', 'query' => []]) ?>

<main class="wrap view-page stack automation-page">
    <section class="card stack">
        <h2 class="section-title"><?= $this->icon('bolt') ?> <?= $this->te('Automation') ?></h2>
        <p class="muted"><?= $this->te('Automations do routine work for you. They act with the permissions of the person who set them up (or who pressed the button), and everything they do is written in the run log below.') ?></p>

        <?php foreach ($kinds as $key => [$icon, $label, $help]): ?>
            <?php if (!isset($byKind[$key])) {
                continue;
            } ?>
            <h3 class="automation-kind"><?= $this->icon($icon) ?> <?= $this->te($label) ?></h3>
            <ul class="automation-list">
                <?php foreach ($byKind[$key] as $automation): ?>
                    <li class="automation-item <?= $automation->enabled ? '' : 'is-paused' ?>">
                        <div class="automation-text">
                            <?php $sentence = $described[$automation->id] ?? ''; $own = !str_starts_with($sentence, $automation->name); ?>
                            <strong dir="auto"><?= $this->e($own ? $automation->name : $sentence) ?></strong>
                            <?php if (!$automation->enabled): ?><span class="pill"><?= $this->icon('pause') ?><?= $this->te('Paused') ?></span><?php endif; ?>
                            <?php if ($own): ?><p dir="auto"><?= $this->e($sentence) ?></p><?php endif; ?>
                            <p class="muted small">
                                <?= $this->te('Set up by :name', ['name' => $people[$automation->createdBy]->name ?? $this->t('Someone')]) ?>
                                <?php if ($automation->lastRunAt !== null): ?> · <?= $this->te('last ran :when', ['when' => $timeAgo->format(new DateTimeImmutable($automation->lastRunAt))]) ?><?php else: ?> · <?= $this->te('has not run yet') ?><?php endif; ?>
                            </p>
                            <?php if ($automation->failures > 0 && isset($failures[$automation->id])): $failed = $failures[$automation->id]; ?>
                                <p class="automation-failure small" role="note"><?= $this->icon('flag') ?>
                                    <span><?= $this->te('Failed :count time(s) in a row. Last time (:when): :reason', [
                                        'count'  => (string) $automation->failures,
                                        'when'   => $timeAgo->format(new DateTimeImmutable($failed->createdAt)),
                                        'reason' => $detail($failed->detail),
                                    ]) ?></span></p>
                            <?php endif; ?>
                        </div>
                        <div class="automation-tools">
                            <a class="btn btn-ghost" href="<?= $this->url('/automations/' . $automation->publicId . '/edit') ?>"><?= $this->icon('edit') ?><span><?= $this->te('Edit') ?></span></a>
                            <form action="<?= $this->url('/automations/' . $automation->publicId . '/duplicate') ?>" method="post">
                                <?= $this->csrfField() ?>
                                <button class="btn btn-ghost" type="submit" title="<?= $this->te('Duplicate (the copy starts paused)') ?>"><?= $this->icon('copy') ?><span class="visually-hidden"><?= $this->te('Duplicate (the copy starts paused)') ?></span></button>
                            </form>
                            <form action="<?= $this->url('/automations/' . $automation->publicId . '/toggle') ?>" method="post">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="on" value="<?= $automation->enabled ? '0' : '1' ?>">
                                <button class="btn btn-ghost" type="submit"><?= $this->icon($automation->enabled ? 'pause' : 'play') ?><span><?= $this->te($automation->enabled ? 'Pause' : 'Turn on') ?></span></button>
                            </form>
                            <form action="<?= $this->url('/automations/' . $automation->publicId . '/delete') ?>" method="post" data-confirm="<?= $this->te('Delete this automation? Its lines in the run log are deleted too.') ?>">
                                <?= $this->csrfField() ?>
                                <button class="btn btn-ghost btn-danger" type="submit"><?= $this->icon('trash') ?><span class="visually-hidden"><?= $this->te('Delete') ?></span></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
        <?php if ($automations === []): ?>
            <p class="empty-note"><?= $this->te('Nothing is automated on this board yet. Add your first automation below.') ?></p>
        <?php endif; ?>
    </section>

    <section class="card stack" id="new">
        <h2 class="section-title"><?= $this->icon('plus') ?> <?= $this->te('New automation') ?></h2>
        <nav class="view-tabs kind-tabs" aria-label="<?= $this->te('Kind of automation') ?>">
            <?php foreach ($kinds as $key => [$icon, $label]): ?>
                <a href="<?= $this->url($base . '?kind=' . $key . '#new') ?>" class="<?= $key === $kind ? 'is-active' : '' ?>" <?= $key === $kind ? 'aria-current="page"' : '' ?>><?= $this->icon($icon) ?><span><?= $this->te($label) ?></span></a>
            <?php endforeach; ?>
        </nav>
        <p class="muted"><?= $this->te($kinds[$kind][2]) ?></p>

        <?= $this->partial('automation/form', ['kind' => $kind, 'action' => $base, 'submit' => 'Save automation', 'values' => [], 'options' => $options]) ?>
    </section>

    <section class="card stack" id="log">
        <h2 class="section-title"><?= $this->icon('activity') ?> <?= $this->te('Run log') ?></h2>
        <?php if ($runs === []): ?>
            <p class="empty-note"><?= $this->te('Nothing has run yet.') ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table run-log">
                    <thead><tr>
                        <th scope="col"><?= $this->te('When') ?></th>
                        <th scope="col"><?= $this->te('Automation') ?></th>
                        <th scope="col"><?= $this->te('Card') ?></th>
                        <th scope="col"><?= $this->te('Result') ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($runs as $run): ?>
                            <tr class="run-<?= $this->e($run->status) ?>">
                                <td><?= $this->e($timeAgo->format(new DateTimeImmutable($run->createdAt))) ?></td>
                                <td dir="auto"><?= $this->e($run->automationName) ?></td>
                                <td dir="auto"><?= $run->cardId !== null ? $this->e($cardTitles[$run->cardId] ?? '') : '–' ?></td>
                                <td><span class="pill run-status"><?= $this->te(['ok' => 'Done', 'failed' => 'Failed', 'skipped' => 'Skipped'][$run->status] ?? $run->status) ?></span>
                                    <span dir="auto"><?= $this->e($detail($run->detail)) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
