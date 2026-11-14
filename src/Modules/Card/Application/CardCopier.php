<?php

declare(strict_types=1);

namespace App\Modules\Card\Application;

use App\Modules\Board\Contract\Events\BoardCopied;
use App\Modules\Card\Contract\Events\CardCopied;
use App\Modules\Card\Domain\CardRepository;
use App\Shared\Events\EventBus;
use App\Support\Clock;

/**
 * Copies the open cards of a board into its copy, list by list and in the
 * same order. Titles, descriptions and dates come along; completion,
 * people, comments and history do not, so the copy starts fresh.
 */
final class CardCopier
{
    public function __construct(private CardRepository $cards, private EventBus $events, private Clock $clock)
    {
    }

    public function onBoardCopied(BoardCopied $event): void
    {
        if (!$event->withCards) {
            return;
        }

        foreach ($this->cards->openCardsByList($event->sourceBoardId) as $listId => $cards) {
            $toList = $event->listMap[$listId] ?? null;
            if ($toList === null) {
                continue;
            }
            foreach ($cards as $card) {
                $copy = $this->cards->create($event->boardId, $toList, $card->title, $this->cards->positionAtEnd($toList), $event->actorId, [
                    'description' => $card->description,
                    'due_at'      => $card->dueAt,
                ]);
                if ($card->startAt !== null) {
                    $this->cards->update($copy->id, ['start_at' => $card->startAt]);
                }
                $this->events->publish(new CardCopied($card->id, $copy->id, $event->boardId, $event->actorId, $this->clock->now()));
            }
        }
    }
}
