<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application;

use App\Modules\Card\Contract\CardQueries;
use App\Modules\Notification\Domain\NotificationStore;
use App\Support\Clock;

/**
 * Reminders about due dates, for a scheduled job (bin/reminders.php):
 *   - "due soon": due today or tomorrow;
 *   - "overdue": the due date passed in the last week.
 * The people assigned to the card and its watchers hear about it. Each
 * reminder has a key made of the card and its due date, so running the job
 * every few minutes still sends each one once, and moving the date sends
 * a new one.
 */
final class DueReminders
{
    public function __construct(
        private CardQueries $cards,
        private NotificationStore $store,
        private Notifier $notifier,
        private Clock $clock,
    ) {
    }

    /** @return int how many notifications were sent */
    public function run(): int
    {
        $today = $this->clock->now();
        $sent = 0;

        $soon = $this->cards->dueBetween($today->format('Y-m-d'), $today->modify('+1 day')->format('Y-m-d'));
        $late = $this->cards->dueBetween($today->modify('-7 days')->format('Y-m-d'), $today->modify('-1 day')->format('Y-m-d'));
        // Every card's assignees in one query.
        $assignees = $this->cards->membersForCards(array_map(static fn ($card) => $card->id, [...$soon, ...$late]));

        foreach (['card.due_soon' => $soon, 'card.overdue' => $late] as $type => $cards) {
            foreach ($cards as $card) {
                $people = [...($assignees[$card->id] ?? []), ...$this->store->watchersOfCard($card->id, $card->boardId)];
                $sent += $this->notifier->notify($people, $type, null, $card->boardId, $card->id, $card->title,
                    ['date' => (string) $card->dueDate()], '/cards/' . $card->publicId, $type . ':' . $card->id . ':' . $card->dueDate());
            }
        }

        return $sent;
    }
}
