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
 *
 * A card can choose its own reminder instead: a number of days before the
 * due date, at an hour (app time zone), which replaces "due soon"; or none
 * at all. Done and archived cards get nothing.
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

        // Cards with their own reminder, due within the next week.
        $own = array_values(array_filter($this->cards->dueBetween($today->format('Y-m-d'), $today->modify('+7 days')->format('Y-m-d')),
            static fn ($card) => $card->remind !== null && ctype_digit($card->remind)));
        $assignees += $this->cards->membersForCards(array_map(static fn ($card) => $card->id, $own));
        foreach ($own as $card) {
            $fires = (new \DateTimeImmutable((string) $card->dueDate()))->modify('-' . (int) $card->remind . ' days')->setTime($card->remindHour, 0);
            if ($today < $fires) {
                continue;
            }
            $people = [...($assignees[$card->id] ?? []), ...$this->store->watchersOfCard($card->id, $card->boardId)];
            $sent += $this->notifier->notify($people, 'card.reminder', null, $card->boardId, $card->id, $card->title,
                ['date' => (string) $card->dueDate()], '/cards/' . $card->publicId, 'reminder:' . $card->id . ':' . $card->dueDate() . ':' . $card->remind . ':' . $card->remindHour);
        }

        foreach (['card.due_soon' => $soon, 'card.overdue' => $late] as $type => $cards) {
            foreach ($cards as $card) {
                // "none" turns every reminder off; a reminder of its own replaces "due soon".
                if ($card->remind === 'none' || ($type === 'card.due_soon' && $card->remind !== null)) {
                    continue;
                }
                $people = [...($assignees[$card->id] ?? []), ...$this->store->watchersOfCard($card->id, $card->boardId)];
                $sent += $this->notifier->notify($people, $type, null, $card->boardId, $card->id, $card->title,
                    ['date' => (string) $card->dueDate()], '/cards/' . $card->publicId, $type . ':' . $card->id . ':' . $card->dueDate());
            }
        }

        return $sent;
    }
}
