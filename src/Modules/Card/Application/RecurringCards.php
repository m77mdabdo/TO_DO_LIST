<?php

declare(strict_types=1);

namespace App\Modules\Card\Application;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Database\Transactions;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

/**
 * Cards copied on a schedule: every day, on chosen weekdays, or on a day of
 * the month, at an hour (app time zone). This is separate from a repeating
 * due date: here a new card is made each time and the original stays.
 *
 * The scheduler (bin/automation.php) calls run(). Each copy first claims
 * its time in card_repeat_runs, whose primary key is (card, time); a second
 * run for the same time, from a retry or two schedulers at once, finds the
 * claim taken and makes nothing. If the scheduler was off for a while, only
 * the most recent missed time is made, not every one.
 */
final class RecurringCards
{
    public const FREQUENCIES = CardSummary::REPEAT_FREQUENCIES;
    public const WEEKDAYS = CardSummary::WEEKDAYS;

    /** What a copy keeps from the original card. */
    private const KEEP = ['description', 'labels', 'checklists', 'members', 'fields'];

    public function __construct(
        private PDO $db,
        private CardService $cards,
        private CardQueries $queries,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private Clock $clock,
        private Transactions $transactions,
    ) {
    }

    /** @return array{frequency: string, weekdays: list<string>, month_day: int, hour: int, list_id: int, next_at: string, last_run_at: ?string, last_result: ?string}|null */
    public function forCard(int $cardId): ?array
    {
        $stmt = $this->db->prepare('SELECT frequency, weekdays, month_day, hour, list_id, next_at, last_run_at, last_result FROM card_repeats WHERE card_id = ?');
        $stmt->execute([$cardId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'frequency'   => (string) $row['frequency'],
            'weekdays'    => array_values(array_filter(explode(',', (string) $row['weekdays']), static fn (string $d) => $d !== '')),
            'month_day'   => (int) $row['month_day'],
            'hour'        => (int) $row['hour'],
            'list_id'     => (int) $row['list_id'],
            'next_at'     => (string) $row['next_at'],
            'last_run_at' => $row['last_run_at'] !== null ? (string) $row['last_run_at'] : null,
            'last_result' => $row['last_result'] !== null ? (string) $row['last_result'] : null,
        ];
    }

    /**
     * Turns copying on (or changes it), or off with frequency "".
     *
     * @param array<string, mixed> $input frequency, weekdays (list), month_day, hour, list_id
     */
    public function set(int $actorId, CardSummary $card, array $input): void
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::EditCards);
        $board = $this->boards->find($card->boardId);
        if ($board === null || $board->archived || $card->archived) {
            throw AccessDenied::forbidden();
        }

        $frequency = (string) ($input['frequency'] ?? '');
        if ($frequency === '') {
            $this->db->prepare('DELETE FROM card_repeats WHERE card_id = ?')->execute([$card->id]);

            return;
        }
        if (!isset(self::FREQUENCIES[$frequency])) {
            $this->fail('frequency', 'Choose how often to make a copy.');
        }
        $weekdays = array_values(array_unique(array_filter(array_map('strval', (array) ($input['weekdays'] ?? [])), static fn (string $d) => isset(self::WEEKDAYS[$d]))));
        sort($weekdays);
        if ($frequency === 'weekly' && $weekdays === []) {
            $this->fail('weekdays', 'Tick at least one day of the week.');
        }
        $monthDay = (string) ($input['month_day'] ?? '1');
        if (!ctype_digit($monthDay) || (int) $monthDay < 1 || (int) $monthDay > 31) {
            $this->fail('month_day', 'Choose a day of the month from 1 to 31.');
        }
        $hour = (string) ($input['hour'] ?? '6');
        if (!ctype_digit($hour) || (int) $hour > 23) {
            $this->fail('hour', 'Choose an hour of the day.');
        }
        $listId = (string) ($input['list_id'] ?? '');
        $list = ctype_digit($listId) ? $this->boards->listOnBoard((int) $listId, $card->boardId) : null;
        if ($list === null || $list->archived) {
            $this->fail('list_id', 'Choose a list of this board.');
        }

        $rule = ['frequency' => $frequency, 'weekdays' => $weekdays, 'month_day' => (int) $monthDay, 'hour' => (int) $hour];
        $next = self::nextSlot($rule, $this->clock->now())->format('Y-m-d H:i:s');
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $old = $this->forCard($card->id);

        $this->db->prepare('DELETE FROM card_repeats WHERE card_id = ?')->execute([$card->id]);
        $this->db->prepare('INSERT INTO card_repeats (card_id, board_id, list_id, frequency, weekdays, month_day, hour, next_at, last_run_at, last_result, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$card->id, $card->boardId, $list->id, $frequency, implode(',', $weekdays), (int) $monthDay, (int) $hour, $next, $old['last_run_at'] ?? null, $old['last_result'] ?? null, $actorId, $now]);
    }

    /**
     * Makes the copies whose time has come.
     *
     * @return int how many cards were made
     */
    public function run(): int
    {
        $now = $this->clock->now();
        $stmt = $this->db->prepare('SELECT card_id, frequency, weekdays, month_day, hour, list_id, next_at, created_by FROM card_repeats WHERE next_at <= ? ORDER BY next_at');
        $stmt->execute([$now->format('Y-m-d H:i:s')]);
        $made = 0;

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rule = ['frequency' => (string) $row['frequency'], 'weekdays' => array_values(array_filter(explode(',', (string) $row['weekdays']), static fn ($d) => $d !== '')),
                'month_day' => (int) $row['month_day'], 'hour' => (int) $row['hour']];
            $cardId = (int) $row['card_id'];
            $slot = (new DateTimeImmutable((string) $row['next_at']))->format('YmdH');
            $next = self::nextSlot($rule, $now)->format('Y-m-d H:i:s');
            $result = 'ok';

            // Claim this time first. If the claim is taken, another run already made (or tried) the copy.
            try {
                $this->db->prepare('INSERT INTO card_repeat_runs (card_id, slot, created_at) VALUES (?, ?, ?)')->execute([$cardId, $slot, $now->format('Y-m-d H:i:s')]);
            } catch (PDOException) {
                $this->advance($cardId, (string) $row['next_at'], $next, null);
                continue;
            }

            try {
                $this->transactions->run(function () use ($cardId, $slot, $row): void {
                    $card = $this->queries->find($cardId);
                    if ($card === null || $card->archived) {
                        throw new \RuntimeException('The card is archived, so no copy was made.');
                    }
                    $actor = $row['created_by'] !== null ? (int) $row['created_by'] : 0;
                    $copy = $this->cards->copyCard($actor, $card, $card->boardId, (int) $row['list_id'], $card->title, self::KEEP);
                    $this->db->prepare('UPDATE card_repeat_runs SET new_card_id = ? WHERE card_id = ? AND slot = ?')->execute([$copy->id, $cardId, $slot]);
                });
                $made++;
            } catch (Throwable $e) {
                $result = $e instanceof AccessDenied ? 'The person who set this up can no longer add cards here.'
                    : ($e instanceof ValidationException ? (string) (current($e->errors()) ?: 'The copy could not be made.') : mb_substr($e->getMessage(), 0, 200));
            }

            $this->advance($cardId, (string) $row['next_at'], $next, $result);
        }

        return $made;
    }

    /**
     * The first time after $after that the rule names.
     *
     * @param array{frequency: string, weekdays: list<string>, month_day: int, hour: int} $rule
     */
    public static function nextSlot(array $rule, DateTimeImmutable $after): DateTimeImmutable
    {
        $day = $after->setTime($rule['hour'], 0);
        for ($i = 0; $i < 400; $i++) {
            $candidate = $day->modify('+' . $i . ' days');
            if ($candidate <= $after) {
                continue;
            }
            $fits = match ($rule['frequency']) {
                'weekly'  => in_array($candidate->format('w'), $rule['weekdays'], true),
                'monthly' => (int) $candidate->format('j') === min($rule['month_day'], (int) $candidate->format('t')),
                default   => true,
            };
            if ($fits) {
                return $candidate;
            }
        }

        return $after->modify('+1 day');
    }

    private function advance(int $cardId, string $was, string $next, ?string $result): void
    {
        // Only if nobody moved it meanwhile.
        $sql = $result === null
            ? 'UPDATE card_repeats SET next_at = ? WHERE card_id = ? AND next_at = ?'
            : 'UPDATE card_repeats SET next_at = ?, last_run_at = ?, last_result = ? WHERE card_id = ? AND next_at = ?';
        $args = $result === null ? [$next, $cardId, $was] : [$next, $this->clock->now()->format('Y-m-d H:i:s'), $result, $cardId, $was];
        $this->db->prepare($sql)->execute($args);
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
