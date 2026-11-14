<?php

declare(strict_types=1);

namespace App\Modules\Automation\Application;

use App\I18n\Translator;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Automation\Contract\Automation;
use App\Modules\Automation\Contract\AutomationRun;
use App\Modules\Automation\Domain\AutomationRepository;
use App\Modules\Automation\Domain\Conditions;
use App\Modules\Automation\Domain\RunLog;
use App\Modules\Automation\Domain\Schedule;
use App\Modules\Automation\Domain\Triggers;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Label\Contract\LabelCatalog;
use App\Shared\Automation\AutomationActions;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;

/**
 * Setting automations up: board managers create, pause and delete them;
 * people who can edit cards press the buttons. Also turns an automation
 * into a readable sentence and offers the choices for the form.
 */
final class AutomationService
{
    public const MAX_PER_BOARD = 50;
    public const MAX_ACTIONS = 8;
    public const MAX_CONDITIONS = 4;
    public const NAME_MAX = 120;
    public const TEXT_MAX = 2000;
    public const DAY_CHOICES = Automation::DUE_DAY_CHOICES;

    public function __construct(
        private AutomationRepository $automations,
        private RunLog $runs,
        private AutomationActions $actions,
        private AutomationEngine $engine,
        private BoardDirectory $boards,
        private LabelCatalog $labels,
        private AccessGate $gate,
        private UserDirectory $users,
        private Translator $translator,
    ) {
    }

    public function canManage(int $userId, BoardSummary $board): bool
    {
        return !$board->archived && $this->gate->can($userId, $board->id, Permission::ManageSettings);
    }

    public function canPress(int $userId, int $boardId): bool
    {
        return $this->gate->can($userId, $boardId, Permission::EditCards);
    }

    /** @return list<Automation> */
    public function forBoard(int $userId, BoardSummary $board): array
    {
        $this->gate->authorize($userId, $board->id, Permission::ManageSettings);

        return $this->automations->forBoard($board->id);
    }

    /** @return list<Automation> enabled buttons of one kind (card_button or board_button) */
    public function buttons(int $boardId, string $kind): array
    {
        return array_values(array_filter($this->automations->forBoard($boardId), static fn (Automation $a) => $a->kind === $kind && $a->enabled));
    }

    /** @return list<AutomationRun> */
    public function runLog(int $userId, BoardSummary $board): array
    {
        $this->gate->authorize($userId, $board->id, Permission::ManageSettings);

        return $this->runs->latest($board->id);
    }

    /**
     * Form input:
     *   kind, name,
     *   trigger       "card.moved:12" (rules),
     *   scope         a list id or "" (board buttons, schedules, due dates),
     *   every, day, hour (schedules), when, days (due dates),
     *   conditions[]  "in_list:12", "no_due", ...
     *   actions[i][do]   "card.move:12", "card.complete", ...
     *   actions[i][text] for actions that take text.
     *
     * @param array<string, mixed> $input
     */
    public function create(int $actorId, BoardSummary $board, array $input): Automation
    {
        $this->gate->authorize($actorId, $board->id, Permission::ManageSettings);
        if ($board->archived) {
            throw AccessDenied::forbidden();
        }

        $kind = (string) ($input['kind'] ?? '');
        $errors = [];
        if (!in_array($kind, Automation::KINDS, true)) {
            throw $this->invalid(['kind' => 'Choose what kind of automation this is.']);
        }
        if ($this->automations->countForBoard($board->id) >= self::MAX_PER_BOARD) {
            throw $this->invalid(['kind' => 'A board can have at most 50 automations.']);
        }

        $lists = array_column(array_map(static fn ($l) => ['id' => $l->id, 'name' => $l->name], $this->boards->openLists($board->id)), 'name', 'id');
        $labels = array_column(array_map(static fn ($l) => ['id' => $l->id, 'name' => $l->name], $this->labels->forBoard($board->id)), 'name', 'id');
        $people = $this->gate->peopleOn($board->id);

        // Trigger
        [$trigger, $triggerValue] = [null, null];
        if ($kind === 'rule') {
            [$trigger, $triggerValue] = array_pad(explode(':', (string) ($input['trigger'] ?? ''), 2), 2, null);
            $param = Triggers::TYPES[$trigger] ?? null;
            $triggerValue = $triggerValue === '' ? null : $triggerValue;
            if ($param === null
                || (Triggers::needsValue((string) $trigger) && $triggerValue === null)
                || ($triggerValue !== null && !array_key_exists((int) $triggerValue, str_starts_with($param, 'list') ? $lists : $labels))) {
                $errors['trigger'] = 'Choose when it should run.';
            }
        }

        // Scope and timing
        $scope = null;
        if (in_array($kind, ['board_button', 'schedule', 'due'], true) && (string) ($input['scope'] ?? '') !== '') {
            $scope = (int) $input['scope'];
            if (!isset($lists[$scope])) {
                $errors['scope'] = 'That list is not available.';
            }
        }

        $schedule = [];
        if ($kind === 'schedule') {
            $every = in_array($input['every'] ?? '', Schedule::EVERY, true) ? (string) $input['every'] : 'day';
            $schedule = [
                'every' => $every,
                // The form has a weekday and a day-of-month field; the one that fits "every" counts.
                'day'   => match ($every) {
                    'week'  => max(0, min(6, (int) ($input['weekday'] ?? 1))),
                    'month' => max(1, min(28, (int) ($input['monthday'] ?? 1))),
                    default => 0,
                },
                'hour'  => max(0, min(23, (int) ($input['hour'] ?? 8))),
            ];
        } elseif ($kind === 'due') {
            $schedule = [
                'when' => in_array($input['when'] ?? '', ['before', 'on', 'after'], true) ? (string) $input['when'] : 'on',
                'days' => in_array((int) ($input['days'] ?? 0), self::DAY_CHOICES, true) ? (int) $input['days'] : 1,
            ];
            if ($schedule['when'] === 'on') {
                $schedule['days'] = 0;
            }
        }

        // Conditions (card buttons run on the card you press them on, so they have none)
        $conditions = [];
        if ($kind !== 'card_button') {
            foreach (array_slice(array_filter((array) ($input['conditions'] ?? []), static fn ($c) => is_string($c) && $c !== ''), 0, self::MAX_CONDITIONS) as $raw) {
                [$type, $value] = array_pad(explode(':', $raw, 2), 2, '');
                $needs = Conditions::TYPES[$type] ?? null;
                $known = str_contains($type, 'list') ? isset($lists[(int) $value]) : isset($labels[(int) $value]);
                if ($needs === null || ($needs && !$known)) {
                    $errors['conditions'] = 'One of the conditions is not available.';
                    continue;
                }
                $conditions[] = ['type' => $type, 'value' => $needs ? (string) (int) $value : ''];
            }
        }

        // Actions
        $actions = [];
        foreach ((array) ($input['actions'] ?? []) as $row) {
            $raw = is_array($row) ? (string) ($row['do'] ?? '') : '';
            if ($raw === '') {
                continue;
            }
            [$key, $value] = array_pad(explode(':', $raw, 2), 2, '');
            $type = $this->actions->get($key);
            $text = trim((string) ($row['text'] ?? ''));
            $valid = $type !== null && match ($type->param) {
                'list'   => isset($lists[(int) $value]),
                'label'  => isset($labels[(int) $value]),
                'person' => $value === 'me' || $value === 'trigger' || isset($people[(int) $value]),
                'days'   => in_array((int) $value, self::DAY_CHOICES, true),
                'text'   => $text !== '' && mb_strlen($text) <= self::TEXT_MAX,
                default  => true,
            };
            if (!$valid) {
                $errors['actions'] = $type?->param === 'text' ? 'Write the text for each action that needs it.' : 'One of the actions is not available.';
                continue;
            }
            // A card button acts on its card; list-wide actions only make sense on lists.
            if ($type->target === 'list' && $kind === 'card_button') {
                $errors['actions'] = 'Sorting a list is not something a card button can do.';
                continue;
            }
            $actions[] = ['type' => $key, 'value' => in_array($type->param, ['none', 'text'], true) ? '' : $value, 'text' => $type->param === 'text' ? $text : ''];
        }
        if ($actions === []) {
            $errors['actions'] ??= 'Add at least one action.';
        } elseif (count($actions) > self::MAX_ACTIONS) {
            $errors['actions'] = 'An automation can do at most 8 things.';
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' && $kind !== 'rule' && $kind !== 'due' && $kind !== 'schedule') {
            $errors['name'] = 'Give the button a name.';
        } elseif (mb_strlen($name) > self::NAME_MAX) {
            $errors['name'] = 'The name is too long.';
        }

        if ($errors !== []) {
            throw $this->invalid($errors);
        }

        $draft = new Automation(0, '', $board->id, $kind, $name, $trigger, $triggerValue, $scope, $conditions, $actions, $schedule, true, 0, $actorId, '', null);
        if ($name === '') {
            $name = mb_substr($this->describe($draft, $lists, $labels, []), 0, self::NAME_MAX);
        }

        return $this->automations->create([
            'board_id'      => $board->id,
            'kind'          => $kind,
            'name'          => $name,
            'trigger_type'  => $trigger,
            'trigger_value' => $triggerValue,
            'scope_list_id' => $scope,
            'conditions'    => json_encode($conditions, JSON_UNESCAPED_UNICODE),
            'actions'       => json_encode($actions, JSON_UNESCAPED_UNICODE),
            'schedule'      => json_encode((object) $schedule),
            'created_by'    => $actorId,
        ]);
    }

    /** The automation, only for someone who manages its board. */
    public function manageable(int $userId, string $publicId): Automation
    {
        $automation = $this->automations->findByPublicId($publicId) ?? throw AccessDenied::hidden();
        if (!$this->gate->can($userId, $automation->boardId, Permission::ViewBoard)) {
            throw AccessDenied::hidden();
        }
        $this->gate->authorize($userId, $automation->boardId, Permission::ManageSettings);

        return $automation;
    }

    public function setEnabled(int $userId, string $publicId, bool $enabled): Automation
    {
        $automation = $this->manageable($userId, $publicId);
        $this->automations->setEnabled($automation->id, $enabled);

        return $automation;
    }

    public function delete(int $userId, string $publicId): Automation
    {
        $automation = $this->manageable($userId, $publicId);
        $this->automations->delete($automation->id);

        return $automation;
    }

    /** Presses a card button. Returns null when it worked, or why not (untranslated). */
    public function pressCard(int $userId, string $publicId, CardSummary $card): ?string
    {
        $button = $this->button($userId, $publicId, 'card_button', $card->boardId);

        return $this->engine->pressCardButton($button, $card, $userId);
    }

    /** @return array{button: Automation, ok: int, failed: int, error: ?string} */
    public function pressBoard(int $userId, string $publicId): array
    {
        $button = $this->automations->findByPublicId($publicId) ?? throw AccessDenied::hidden();
        $button = $this->button($userId, $publicId, 'board_button', $button->boardId);

        return ['button' => $button] + $this->engine->pressBoardButton($button, $userId);
    }

    /**
     * Choices for the form, as value => label, from what is on the board now.
     *
     * @return array{triggers: array<string, string>, conditions: array<string, string>, actions: array<string, string>, textActions: list<string>, lists: array<int, string>}
     */
    public function options(BoardSummary $board, int $viewerId): array
    {
        $lists = array_column(array_map(static fn ($l) => ['id' => $l->id, 'name' => $l->name], $this->boards->openLists($board->id)), 'name', 'id');
        $labels = array_column(array_map(static fn ($l) => ['id' => $l->id, 'name' => $l->name], $this->labels->forBoard($board->id)), 'name', 'id');
        $people = $this->users->findMany(array_keys($this->gate->peopleOn($board->id)));
        $q = static fn (string $name): string => '“' . $name . '”';
        $t = fn (string $text, array $values = []): string => $this->translator->t($text, $values);

        $triggers = ['card.created' => $t('When a card is added to the board')];
        foreach ($lists as $id => $name) {
            $triggers['card.created:' . $id] = $t('When a card is added to :list', ['list' => $q($name)]);
        }
        foreach ($lists as $id => $name) {
            $triggers['card.moved:' . $id] = $t('When a card is moved into :list', ['list' => $q($name)]);
        }
        $triggers['card.completed'] = $t('When a card is marked complete');
        $triggers['checklist.done'] = $t('When every checklist item on a card is done');
        $triggers['member.added'] = $t('When someone is added to a card');
        $triggers['comment.posted'] = $t('When someone comments on a card');
        $triggers['label.added'] = $t('When any label is added to a card');
        foreach ($labels as $id => $name) {
            $triggers['label.added:' . $id] = $t('When the label :label is added to a card', ['label' => $q($name)]);
        }

        $conditions = [];
        foreach ($lists as $id => $name) {
            $conditions['in_list:' . $id] = $t('the card is in :list', ['list' => $q($name)]);
            $conditions['not_in_list:' . $id] = $t('the card is not in :list', ['list' => $q($name)]);
        }
        foreach ($labels as $id => $name) {
            $conditions['has_label:' . $id] = $t('the card has the label :label', ['label' => $q($name)]);
            $conditions['lacks_label:' . $id] = $t('the card does not have the label :label', ['label' => $q($name)]);
        }
        foreach (['has_members' => 'someone is on the card', 'no_members' => 'nobody is on the card', 'has_due' => 'the card has a due date', 'no_due' => 'the card has no due date', 'overdue' => 'the card is overdue', 'complete' => 'the card is complete', 'not_complete' => 'the card is not complete'] as $type => $label) {
            $conditions[$type] = $t($label);
        }

        $actions = [];
        $textActions = [];
        foreach ($this->actions->all() as $type) {
            $label = fn (string $value): string => $t($type->label, ['value' => $value]);
            switch ($type->param) {
                case 'list':
                    foreach ($lists as $id => $name) {
                        $actions[$type->key . ':' . $id] = $label($q($name));
                    }
                    break;
                case 'label':
                    foreach ($labels as $id => $name) {
                        $actions[$type->key . ':' . $id] = $label($q($name));
                    }
                    break;
                case 'person':
                    $actions[$type->key . ':me'] = $label($t('the person it runs as'));
                    $actions[$type->key . ':trigger'] = $label($t('the person who set it off'));
                    foreach ($people as $id => $person) {
                        $actions[$type->key . ':' . $id] = $label($person->name);
                    }
                    break;
                case 'days':
                    foreach (self::DAY_CHOICES as $days) {
                        $actions[$type->key . ':' . $days] = $label((string) $days);
                    }
                    break;
                case 'text':
                    $textActions[] = $type->key;
                    $actions[$type->key] = $label('') . ' …';
                    break;
                default:
                    $actions[$type->key] = $label('');
            }
        }

        return ['triggers' => $triggers, 'conditions' => $conditions, 'actions' => $actions, 'textActions' => $textActions, 'lists' => $lists];
    }

    /**
     * The automation as one sentence in the reader's language, e.g.
     * "When a card is moved into “Done”, if the card has no due date: mark the card complete, then archive the card."
     *
     * @param array<int, string> $lists
     * @param array<int, string> $labels
     * @param array<int, string> $names user id => name
     */
    public function describe(Automation $a, array $lists, array $labels, array $names): string
    {
        $q = static fn (string $name): string => '“' . $name . '”';
        $t = fn (string $text, array $values = []): string => $this->translator->t($text, $values);
        $list = static fn (?string $id): string => $q($lists[(int) $id] ?? '?');
        $label = static fn (?string $id): string => $q($labels[(int) $id] ?? '?');

        $when = match ($a->kind) {
            'rule' => match ($a->trigger) {
                'card.created'   => $a->triggerValue !== null ? $t('When a card is added to :list', ['list' => $list($a->triggerValue)]) : $t('When a card is added to the board'),
                'card.moved'     => $t('When a card is moved into :list', ['list' => $list($a->triggerValue)]),
                'card.completed' => $t('When a card is marked complete'),
                'checklist.done' => $t('When every checklist item on a card is done'),
                'member.added'   => $t('When someone is added to a card'),
                'comment.posted' => $t('When someone comments on a card'),
                'label.added'    => $a->triggerValue !== null ? $t('When the label :label is added to a card', ['label' => $label($a->triggerValue)]) : $t('When any label is added to a card'),
                default          => '?',
            },
            'schedule' => match ($a->schedule['every'] ?? 'day') {
                'week'  => $t('Every :day at :hour', ['day' => $t(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][(int) ($a->schedule['day'] ?? 0)]), 'hour' => sprintf('%02d:00', (int) ($a->schedule['hour'] ?? 8))]),
                'month' => $t('On day :day of every month at :hour', ['day' => (int) ($a->schedule['day'] ?? 1), 'hour' => sprintf('%02d:00', (int) ($a->schedule['hour'] ?? 8))]),
                default => $t('Every day at :hour', ['hour' => sprintf('%02d:00', (int) ($a->schedule['hour'] ?? 8))]),
            },
            'due' => match ($a->schedule['when'] ?? 'on') {
                'before' => $t(':days day(s) before a card is due', ['days' => (int) ($a->schedule['days'] ?? 1)]),
                'after'  => $t(':days day(s) after a card was due, if it is not done', ['days' => (int) ($a->schedule['days'] ?? 1)]),
                default  => $t('On the day a card is due'),
            },
            'card_button'  => $t('When the card button is pressed'),
            default        => $t('When the board button is pressed'),
        };

        if ($a->scopeListId !== null) {
            $when .= ' ' . $t('(cards in :list)', ['list' => $list((string) $a->scopeListId)]);
        }

        $if = [];
        foreach ($a->conditions as $c) {
            $if[] = match ($c['type']) {
                'in_list'     => $t('the card is in :list', ['list' => $list($c['value'])]),
                'not_in_list' => $t('the card is not in :list', ['list' => $list($c['value'])]),
                'has_label'   => $t('the card has the label :label', ['label' => $label($c['value'])]),
                'lacks_label' => $t('the card does not have the label :label', ['label' => $label($c['value'])]),
                'has_members' => $t('someone is on the card'),
                'no_members'  => $t('nobody is on the card'),
                'has_due'     => $t('the card has a due date'),
                'no_due'      => $t('the card has no due date'),
                'overdue'     => $t('the card is overdue'),
                'complete'    => $t('the card is complete'),
                default       => $t('the card is not complete'),
            };
        }

        $do = [];
        foreach ($a->actions as $action) {
            $type = $this->actions->get($action['type']);
            if ($type === null) {
                $do[] = $t('(an action that is no longer available)');
                continue;
            }
            $value = match ($type->param) {
                'list'   => $list($action['value']),
                'label'  => $label($action['value']),
                'person' => match ($action['value']) {
                    'me'      => $t('the person it runs as'),
                    'trigger' => $t('the person who set it off'),
                    default   => $names[(int) $action['value']] ?? '?',
                },
                'days'   => $action['value'],
                default  => '',
            };
            $do[] = trim($t($type->label, ['value' => $value]) . ($type->param === 'text' ? ': ' . $q(mb_strimwidth($action['text'], 0, 60, '…')) : ''));
        }

        $sentence = $when . ($if !== [] ? $t(', if :conditions', ['conditions' => implode($t(' and '), $if)]) : '') . ': ';

        return $sentence . implode($t(', then '), array_map(static fn (string $s) => mb_strtolower(mb_substr($s, 0, 1)) . mb_substr($s, 1), $do)) . '.';
    }

    private function button(int $userId, string $publicId, string $kind, int $boardId): Automation
    {
        $button = $this->automations->findByPublicId($publicId);
        if ($button === null || $button->kind !== $kind || $button->boardId !== $boardId || !$this->gate->can($userId, $boardId, Permission::ViewBoard)) {
            throw AccessDenied::hidden();
        }
        $this->gate->authorize($userId, $boardId, Permission::EditCards);
        if (!$button->enabled || ($this->boards->find($boardId)?->archived ?? true)) {
            throw AccessDenied::forbidden();
        }

        return $button;
    }

    /** @param array<string, string> $errors */
    private function invalid(array $errors): ValidationException
    {
        return new ValidationException(new ValidationResult($errors));
    }
}
