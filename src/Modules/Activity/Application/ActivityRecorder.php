<?php

declare(strict_types=1);

namespace App\Modules\Activity\Application;

use App\Modules\Activity\Domain\ActivityLog;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\Events\BoardChanged;
use App\Modules\Board\Contract\Events\BoardCreated;
use App\Modules\Board\Contract\Events\ListChanged;
use App\Modules\Board\Contract\Events\ListCreated;
use App\Modules\Card\Contract\Events\CardChanged;
use App\Modules\Card\Contract\Events\CardCreated;
use App\Modules\Card\Contract\Events\CardDeleted;
use App\Modules\Card\Contract\Events\CardMoved;
use App\Modules\Label\Contract\Events\LabelAttached;
use App\Modules\Label\Contract\Events\LabelDetached;
use App\Shared\Events\DomainEvent;

/**
 * Turns other modules' events into the activity log. Names (list names,
 * titles) are copied in when the event happens, so history keeps saying
 * what things were called at the time.
 */
final class ActivityRecorder
{
    public function __construct(private ActivityLog $log, private BoardDirectory $boards)
    {
    }

    /** @return list<string> event names this recorder handles */
    public static function events(): array
    {
        return [
            BoardCreated::name(), BoardChanged::name(), ListCreated::name(), ListChanged::name(),
            CardCreated::name(), CardChanged::name(), CardMoved::name(), CardDeleted::name(),
            LabelAttached::name(), LabelDetached::name(),
        ];
    }

    public function record(DomainEvent $event): void
    {
        match (true) {
            $event instanceof BoardCreated  => $this->log->record($event->boardId, null, $event->actorId, 'board.created', $event->boardName),
            $event instanceof BoardChanged  => $this->log->record($event->boardId, null, $event->actorId, 'board.' . $event->change, (string) ($event->details['to'] ?? ''), $event->details),
            $event instanceof ListCreated   => $this->log->record($event->boardId, null, $event->actorId, 'list.created', $event->listName),
            $event instanceof ListChanged   => $this->log->record($event->boardId, null, $event->actorId, 'list.' . $event->change, (string) ($event->details['to'] ?? $event->details['name'] ?? ''), $event->details),
            $event instanceof CardCreated   => $this->log->record($event->boardId, $event->cardId, $event->actorId, 'card.created', $event->title, ['list' => $this->listName($event->listId, $event->boardId)]),
            $event instanceof CardChanged   => $this->log->record($event->boardId, $event->cardId, $event->actorId, 'card.' . $event->change, $event->title, $event->details),
            $event instanceof CardMoved     => $event->fromListId === $event->toListId ? null : $this->log->record($event->boardId, $event->cardId, $event->actorId, 'card.moved', $event->title, [
                'from' => $this->listName($event->fromListId, $event->boardId),
                'to'   => $this->listName($event->toListId, $event->boardId),
            ]),
            $event instanceof CardDeleted   => $this->log->record($event->boardId, null, $event->actorId, 'card.deleted', $event->title),
            $event instanceof LabelAttached => $this->log->record($event->boardId, $event->cardId, $event->actorId, 'label.attached', $event->labelName),
            $event instanceof LabelDetached => $this->log->record($event->boardId, $event->cardId, $event->actorId, 'label.detached', $event->labelName),
            default => null,
        };
    }

    private function listName(int $listId, int $boardId): string
    {
        return $this->boards->listOnBoard($listId, $boardId)?->name ?? '';
    }
}
