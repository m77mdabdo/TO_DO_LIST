<?php

declare(strict_types=1);

namespace App\Modules\Automation\Application;

use App\Database\Transactions;
use App\Http\HttpException;
use App\Modules\Automation\Contract\Automation;
use App\Modules\Automation\Domain\AutomationRepository;
use App\Modules\Automation\Domain\Conditions;
use App\Modules\Automation\Domain\RunLog;
use App\Modules\Automation\Domain\Schedule;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Card\Contract\Events\CardChanged;
use App\Modules\Card\Contract\Events\CardCreated;
use App\Modules\Card\Contract\Events\CardMemberChanged;
use App\Modules\Card\Contract\Events\CardMoved;
use App\Modules\Checklist\Contract\ChecklistQueries;
use App\Modules\Checklist\Contract\Events\ChecklistChanged;
use App\Modules\Discussion\Contract\Events\CommentPosted;
use App\Modules\Label\Contract\Events\LabelAttached;
use App\Modules\Label\Contract\LabelCatalog;
use App\Shared\Automation\ActionContext;
use App\Shared\Automation\AutomationActions;
use App\Shared\Events\DomainEvent;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Runs automations: rules when their event happens, buttons when pressed,
 * schedules and due-date automations from the scheduler (bin/automation.php).
 *
 * Every action goes through the owning module's use case as the automation's
 * actor, so permissions are checked as usual. Each run gets a savepoint: a
 * failing automation undoes only its own changes, never the user's action
 * that triggered it, and is written to the run log.
 *
 * Loops: events caused by an automation are handled in the same event
 * round as the change that started it. In one round a rule runs at most once
 * per card, and at most MAX_RUNS_PER_ROUND rules run in all.
 * An automation that fails MAX_FAILURES times in a row is paused.
 */
final class AutomationEngine
{
    public const MAX_RUNS_PER_ROUND = 25;
    public const MAX_FAILURES = 5;
    public const MAX_CARDS = 200;
    /** How far back the scheduler catches up on due-date automations it missed. */
    public const CATCH_UP_DAYS = 7;
    public const KEEP_RUNS_DAYS = 90;

    /** @var array<string, true> "automation id:card id" pairs that ran in this event round */
    private array $ran = [];
    private int $round = -1;

    public function __construct(
        private AutomationRepository $automations,
        private RunLog $runs,
        private AutomationActions $actions,
        private CardQueries $cards,
        private LabelCatalog $labels,
        private ChecklistQueries $checklists,
        private BoardDirectory $boards,
        private Transactions $transactions,
        private Clock $clock,
        private EventBus $events,
    ) {
    }

    /** Called for every card event; runs the board's matching rules. */
    public function handle(DomainEvent $event): void
    {
        $trigger = $this->triggerOf($event);
        if ($trigger === null) {
            return;
        }
        [$type, $value, $boardId, $cardId, $actorId] = $trigger;

        if ($this->events->round() !== $this->round) {
            $this->round = $this->events->round();
            $this->ran = [];
        }

        foreach ($this->automations->rulesFor($boardId, $type) as $rule) {
            if ($rule->triggerValue !== null && $rule->triggerValue !== '' && $rule->triggerValue !== (string) $value) {
                continue;
            }

            $card = $this->cards->find($cardId);
            if ($card === null || $card->archived || !$this->holds($rule, $card)) {
                continue;
            }

            $this->runOnCard($rule, $card, $rule->createdBy, $actorId, guard: true);
        }
    }

    /**
     * A card button, pressed by $userId (who must be allowed to edit cards;
     * the controller checks). Returns null on success, or why it failed.
     */
    public function pressCardButton(Automation $button, CardSummary $card, int $userId): ?string
    {
        return $this->runOnCard($button, $card, $userId, $userId);
    }

    /**
     * A board button: runs on every open card in its scope that meets its
     * conditions (and its list actions once per list).
     *
     * @return array{ok: int, failed: int, error: ?string}
     */
    public function pressBoardButton(Automation $button, int $userId): array
    {
        return $this->transactions->run(fn () => $this->runOnScope($button, $userId, null));
    }

    /**
     * The scheduler: runs schedules whose time has come and due-date
     * automations for cards whose day it is. Safe to run as often as you
     * like: each slot and each card's due date runs once (the run log).
     *
     * @return int how many automations ran
     */
    public function tick(): int
    {
        $now = $this->clock->now();
        $ran = 0;

        foreach ($this->automations->enabledOfKind('schedule') as $schedule) {
            $slot = Schedule::latestSlot($schedule->schedule, $now);
            $key = 'schedule:' . $schedule->id . ':' . $slot->format('YmdH');

            // Never for times before it existed, and once per time.
            if ($slot->format('Y-m-d H:i:s') < $schedule->createdAt || $this->runs->hasRun($key)) {
                continue;
            }

            $this->transactions->run(fn () => $this->runOnScope($schedule, $schedule->createdBy, $key));
            $ran++;
        }

        $today = $now->format('Y-m-d');
        foreach ($this->automations->enabledOfKind('due') as $due) {
            $from = max(substr($due->createdAt, 0, 10), $now->modify('-' . self::CATCH_UP_DAYS . ' days')->format('Y-m-d'));
            // A card fires on its due date shifted by the offset, so look for due dates shifted back.
            $shift = static fn (string $date): string => match ($due->schedule['when'] ?? 'on') {
                'before' => (new \DateTimeImmutable($date))->modify('+' . (int) ($due->schedule['days'] ?? 0) . ' days')->format('Y-m-d'),
                'after'  => (new \DateTimeImmutable($date))->modify('-' . (int) ($due->schedule['days'] ?? 0) . ' days')->format('Y-m-d'),
                default  => $date,
            };

            foreach ($this->cards->dueBetween($shift($from), $shift($today)) as $card) {
                $fires = Schedule::fireDate($due->schedule, (string) $card->dueDate());
                $key = 'due:' . $due->id . ':' . $card->id . ':' . $card->dueDate();
                if ($card->boardId !== $due->boardId || $fires > $today || $fires < $from
                    || ($due->scopeListId !== null && $card->listId !== $due->scopeListId)
                    || $this->runs->hasRun($key) || !$this->holds($due, $card)) {
                    continue;
                }

                $this->transactions->run(fn () => $this->runOnCard($due, $card, $due->createdBy, $due->createdBy, $key));
                $ran++;
            }
        }

        $this->runs->pruneBefore($now->modify('-' . self::KEEP_RUNS_DAYS . ' days')->format('Y-m-d H:i:s'));

        return $ran;
    }

    /**
     * Runs the automation's actions on one card. Returns null on success, or
     * the reason it failed. $guard applies the loop limits (rules from events).
     */
    private function runOnCard(Automation $automation, CardSummary $card, int $actorId, int $triggeredBy, ?string $dedupeKey = null, bool $guard = false): ?string
    {
        if ($guard) {
            $key = $automation->id . ':' . $card->id;
            if (isset($this->ran[$key]) || count($this->ran) >= self::MAX_RUNS_PER_ROUND) {
                $why = isset($this->ran[$key]) ? 'Did not run again on the same card, to avoid a loop.' : 'Stopped a chain of automations that was too long.';
                $this->runs->add($automation->id, $automation->boardId, $card->id, $actorId, 'skipped', self::detail($why));

                return $why;
            }
            $this->ran[$key] = true;
        }

        $error = null;
        try {
            $context = new ActionContext($actorId, $automation->boardId, $card->id, $card->listId, $triggeredBy, $automation->name, self::runKey($automation));
            $this->transactions->attempt(fn () => $this->perform($automation->actions, $context));
        } catch (Throwable $e) {
            $error = self::reason($e);
        }

        $this->finish($automation, $card->id, $actorId, $error === null, self::detail($error ?? 'Done.'), $dedupeKey);

        return $error;
    }

    /**
     * Card actions on each open card in scope that meets the conditions, then
     * list actions once on each list in scope. One log line for the whole run.
     *
     * @return array{ok: int, failed: int, error: ?string}
     */
    private function runOnScope(Automation $automation, int $actorId, ?string $dedupeKey): array
    {
        $lists = array_map(static fn ($l) => $l->id, $this->boards->openLists($automation->boardId));
        if ($automation->scopeListId !== null) {
            $lists = array_values(array_intersect($lists, [$automation->scopeListId]));
        }

        $cardActions = array_values(array_filter($automation->actions, fn (array $a) => ($this->actions->get($a['type'])?->target ?? 'card') === 'card'));
        $listActions = array_values(array_filter($automation->actions, fn (array $a) => $this->actions->get($a['type'])?->target === 'list'));

        $byList = $this->cards->openCardsByList($automation->boardId);
        $cards = [];
        foreach ($lists as $listId) {
            array_push($cards, ...($byList[$listId] ?? []));
        }
        $facts = $this->facts($cards);
        $cards = array_slice(array_values(array_filter($cards, fn (CardSummary $c) => Conditions::hold($automation->conditions, $facts[$c->id]))), 0, self::MAX_CARDS);

        $base = new ActionContext($actorId, $automation->boardId, null, null, $actorId, $automation->name, self::runKey($automation));
        $ok = 0;
        $failed = 0;
        $error = null;
        $attempt = function (callable $work) use (&$ok, &$failed, &$error): void {
            try {
                $this->transactions->attempt($work);
                $ok++;
            } catch (Throwable $e) {
                $failed++;
                $error ??= self::reason($e);
            }
        };

        if ($cardActions !== []) {
            foreach ($cards as $card) {
                $attempt(fn () => $this->perform($cardActions, $base->withCard($card->id, $card->listId)));
            }
        }
        if ($listActions !== []) {
            foreach ($lists as $listId) {
                $attempt(fn () => $this->perform($listActions, $base->withList($listId)));
            }
        }

        $detail = $failed > 0
            ? self::detail('Ran on :ok, failed on :failed: :reason', ['ok' => $ok, 'failed' => $failed, 'reason' => (string) $error])
            : self::detail('Ran on :count card(s) or list(s).', ['count' => $ok]);
        $this->finish($automation, null, $actorId, $failed === 0, $detail, $dedupeKey);

        return ['ok' => $ok, 'failed' => $failed, 'error' => $error];
    }

    /** @param list<array{type: string, value: string, text: string}> $actions */
    private function perform(array $actions, ActionContext $context): void
    {
        foreach ($actions as $action) {
            $type = $this->actions->get($action['type']) ?? throw new RuntimeException('This action is no longer available.');
            $value = $type->param === 'text' ? (string) ($action['text'] ?? '') : (string) ($action['value'] ?? '');

            if ($type->target === 'list' && $context->cardId !== null) {
                // On a card, a list action works on the list the card is in now.
                $card = $this->cards->find($context->cardId) ?? throw new RuntimeException('The card is gone.');
                ($type->run)($context->withList($card->listId), $value);
                continue;
            }

            ($type->run)($context, $value);
        }
    }

    private function finish(Automation $automation, ?int $cardId, int $actorId, bool $ok, string $detail, ?string $dedupeKey): void
    {
        $this->runs->add($automation->id, $automation->boardId, $cardId, $actorId, $ok ? 'ok' : 'failed', $detail, $dedupeKey);
        $failures = $this->automations->recordRun($automation->id, $ok);

        if (!$ok && !$automation->isButton() && $failures >= self::MAX_FAILURES) {
            $this->automations->setEnabled($automation->id, false);
            $this->runs->add($automation->id, $automation->boardId, null, null, 'skipped', self::detail('Paused after :count failures in a row. Fix it and turn it back on.', ['count' => $failures]));
        }
    }

    private function holds(Automation $automation, CardSummary $card): bool
    {
        return $automation->conditions === [] || Conditions::hold($automation->conditions, $this->facts([$card])[$card->id]);
    }

    /**
     * What conditions look at, for many cards in two grouped queries.
     *
     * @param list<CardSummary> $cards
     * @return array<int, array{list: int, labels: list<int>, members: list<int>, due: ?string, complete: bool, today: string}>
     */
    private function facts(array $cards): array
    {
        $ids = array_map(static fn (CardSummary $c) => $c->id, $cards);
        $labels = $this->labels->forCards($ids);
        $members = $this->cards->membersForCards($ids);
        $today = $this->clock->now()->format('Y-m-d');
        $facts = [];

        foreach ($cards as $card) {
            $facts[$card->id] = [
                'list'     => $card->listId,
                'labels'   => array_map(static fn ($l) => $l->id, $labels[$card->id] ?? []),
                'members'  => $members[$card->id] ?? [],
                'due'      => $card->dueDate(),
                'complete' => $card->isComplete(),
                'today'    => $today,
            ];
        }

        return $facts;
    }

    /** @return array{0: string, 1: int|null, 2: int, 3: int, 4: int}|null trigger, value, board, card, actor */
    private function triggerOf(DomainEvent $event): ?array
    {
        return match (true) {
            $event instanceof CardCreated       => ['card.created', $event->listId, $event->boardId, $event->cardId, $event->actorId],
            $event instanceof CardMoved         => $event->fromListId !== $event->toListId ? ['card.moved', $event->toListId, $event->boardId, $event->cardId, $event->actorId] : null,
            $event instanceof CardChanged       => $event->change === 'completed' ? ['card.completed', null, $event->boardId, $event->cardId, $event->actorId] : null,
            $event instanceof LabelAttached     => ['label.added', $event->labelId, $event->boardId, $event->cardId, $event->actorId],
            $event instanceof CardMemberChanged => $event->added ? ['member.added', null, $event->boardId, $event->cardId, $event->actorId] : null,
            $event instanceof CommentPosted     => ['comment.posted', null, $event->boardId, $event->cardId, $event->actorId],
            $event instanceof ChecklistChanged  => $event->change === 'item_completed' && $this->allItemsDone($event->cardId) ? ['checklist.done', null, $event->boardId, $event->cardId, $event->actorId] : null,
            default                             => null,
        };
    }

    private function allItemsDone(int $cardId): bool
    {
        $progress = $this->checklists->progressForCards([$cardId])[$cardId] ?? ['done' => 0, 'total' => 0];

        return $progress['total'] > 0 && $progress['done'] === $progress['total'];
    }

    private static function reason(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return (string) (array_values($e->errors())[0] ?? $e->getMessage());
        }

        if ($e instanceof HttpException) {
            return 'The person it runs as is not allowed to do this.';
        }

        // Our own messages only; database and other errors are not shown to people.
        return $e instanceof RuntimeException && !$e instanceof \PDOException ? $e->getMessage() : 'Something went wrong.';
    }

    /**
     * Log details are stored untranslated with their values, and translated
     * when shown, in the reader's language.
     *
     * @param array<string, string|int> $values
     */
    private static function detail(string $text, array $values = []): string
    {
        return (string) json_encode(['text' => $text, 'values' => $values], JSON_UNESCAPED_UNICODE);
    }

    private static function runKey(Automation $automation): string
    {
        return $automation->id . '-' . bin2hex(random_bytes(6));
    }
}
