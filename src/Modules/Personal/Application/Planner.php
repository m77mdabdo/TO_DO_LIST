<?php

declare(strict_types=1);

namespace App\Modules\Personal\Application;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use PDO;

/**
 * A person's planned work time. A session says "I will work on this card
 * on this day (at this hour, for this long)". It never changes the card:
 * the due date stays what the board says. Sessions are private to the
 * person, and a session on a card they can no longer see is not shown.
 */
final class Planner
{
    public const DURATIONS = [30, 60, 90, 120, 180, 240];
    public const MAX_PER_DAY = 20;
    /** The hours shown as rows of the week; any hour can still be chosen in the forms. */
    public const FIRST_HOUR = 8;
    public const LAST_HOUR = 19;

    /** @var array<string, ?BoardSummary> "user:board" => the board if that person may see it; filled per call */
    private array $boardCache = [];

    public function __construct(
        private PDO $db,
        private CardQueries $cards,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private Clock $clock,
    ) {
    }

    /**
     * Sessions between two days (inclusive) on cards the person can still see.
     *
     * @return list<array{id: int, card: CardSummary, board: BoardSummary, day: string, hour: ?int, minutes: int}>
     */
    public function between(int $userId, string $from, string $to): array
    {
        $this->boardCache = [];
        $stmt = $this->db->prepare('SELECT id, card_id, day, start_minute, minutes FROM planner_sessions WHERE user_id = ? AND day >= ? AND day <= ? ORDER BY day, start_minute, id');
        $stmt->execute([$userId, $from, $to]);

        return $this->visibleSessions($userId, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array{id: int, card: CardSummary, board: BoardSummary, day: string, hour: ?int, minutes: int}> from today on */
    public function upcomingForCard(int $userId, int $cardId): array
    {
        $this->boardCache = [];
        $stmt = $this->db->prepare('SELECT id, card_id, day, start_minute, minutes FROM planner_sessions WHERE user_id = ? AND card_id = ? AND day >= ? ORDER BY day, start_minute, id');
        $stmt->execute([$userId, $cardId, $this->clock->now()->format('Y-m-d')]);

        return $this->visibleSessions($userId, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Open cards given to the person, on boards they can see, that have no
     * planned time from today on.
     *
     * @return list<array{card: CardSummary, board: BoardSummary}>
     */
    public function unplanned(int $userId): array
    {
        $this->boardCache = [];
        $stmt = $this->db->prepare('SELECT DISTINCT card_id FROM planner_sessions WHERE user_id = ? AND day >= ?');
        $stmt->execute([$userId, $this->clock->now()->format('Y-m-d')]);
        $planned = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));

        $out = [];
        foreach ($this->cards->openCardsAssignedTo($userId) as $card) {
            $board = $this->visibleBoard($userId, $card);
            if ($board !== null && !isset($planned[$card->id]) && $card->kind === 'card') {
                $out[] = ['card' => $card, 'board' => $board];
            }
        }
        usort($out, static fn (array $a, array $b) => [$a['card']->dueDate() === null, (string) $a['card']->dueDate()] <=> [$b['card']->dueDate() === null, (string) $b['card']->dueDate()]);

        return $out;
    }

    /** Plans time on a card the person can see. Returns the new session's id. */
    public function plan(int $userId, string $cardPublicId, string $day, string $hour, string $minutes): int
    {
        $this->boardCache = [];
        $card = $this->cards->findByPublicId($cardPublicId);
        if ($card === null || $this->visibleBoard($userId, $card) === null) {
            $this->fail('card', 'Choose a card you can open.');
        }
        [$day, $start, $length] = $this->valid($day, $hour, $minutes === '' ? '60' : $minutes);
        $this->assertRoom($userId, $day, null);
        $this->db->prepare('INSERT INTO planner_sessions (user_id, card_id, day, start_minute, minutes, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $card->id, $day, $start, $length, $this->clock->now()->format('Y-m-d H:i:s')]);

        return (int) $this->db->lastInsertId();
    }

    /** Moves a session to another day or hour, and changes its length if one is given. */
    public function move(int $userId, int $sessionId, string $day, string $hour, string $minutes = ''): void
    {
        $session = $this->own($userId, $sessionId);
        [$day, $start, $length] = $this->valid($day, $hour, $minutes === '' ? (string) $session['minutes'] : $minutes);
        $this->assertRoom($userId, $day, $sessionId);
        $this->db->prepare('UPDATE planner_sessions SET day = ?, start_minute = ?, minutes = ? WHERE id = ?')->execute([$day, $start, $length, $sessionId]);
    }

    /** Takes the session off the plan; the card goes back to the unplanned tasks. */
    public function remove(int $userId, int $sessionId): void
    {
        $this->own($userId, $sessionId);
        $this->db->prepare('DELETE FROM planner_sessions WHERE id = ?')->execute([$sessionId]);
    }

    /** @param list<array<string, mixed>> $rows @return list<array{id: int, card: CardSummary, board: BoardSummary, day: string, hour: ?int, minutes: int}> */
    private function visibleSessions(int $userId, array $rows): array
    {
        $cards = $this->cards->findMany(array_values(array_unique(array_map(static fn ($r) => (int) $r['card_id'], $rows))));
        $out = [];
        foreach ($rows as $row) {
            $card = $cards[(int) $row['card_id']] ?? null;
            $board = $card !== null ? $this->visibleBoard($userId, $card) : null;
            if ($card === null || $board === null) {
                continue;
            }
            $out[] = [
                'id'      => (int) $row['id'],
                'card'    => $card,
                'board'   => $board,
                'day'     => substr((string) $row['day'], 0, 10),
                'hour'    => $row['start_minute'] !== null ? intdiv((int) $row['start_minute'], 60) : null,
                'minutes' => (int) $row['minutes'],
            ];
        }

        return $out;
    }

    private function visibleBoard(int $userId, CardSummary $card): ?BoardSummary
    {
        if ($card->archived) {
            return null;
        }
        $key = $userId . ':' . $card->boardId;
        if (!array_key_exists($key, $this->boardCache)) {
            $board = $this->boards->find($card->boardId);
            $this->boardCache[$key] = $board !== null && !$board->archived && $this->gate->can($userId, $board->id, Permission::ViewBoard) ? $board : null;
        }

        return $this->boardCache[$key];
    }

    /** @return array{id: int, minutes: int} */
    private function own(int $userId, int $sessionId): array
    {
        $stmt = $this->db->prepare('SELECT id, user_id, minutes FROM planner_sessions WHERE id = ?');
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || (int) $row['user_id'] !== $userId) {
            throw AccessDenied::hidden();
        }

        return ['id' => (int) $row['id'], 'minutes' => (int) $row['minutes']];
    }

    /** @return array{0: string, 1: ?int, 2: int} day, start minute (null = any time), length in minutes */
    private function valid(string $day, string $hour, string $minutes): array
    {
        $errors = [];
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day);
        $today = $this->clock->now()->setTime(0, 0);
        if ($date === false || $date->format('Y-m-d') !== $day) {
            $errors['day'] = 'Choose a day.';
        } elseif ($date < $today->modify('-1 year') || $date > $today->modify('+2 years')) {
            $errors['day'] = 'Choose a day within the next two years.';
        }
        if ($hour !== '' && (!ctype_digit($hour) || (int) $hour > 23)) {
            $errors['hour'] = 'Choose an hour of the day.';
        }
        if (!in_array((int) $minutes, self::DURATIONS, true) || !ctype_digit($minutes)) {
            $errors['minutes'] = 'Choose how long you plan to work.';
        }
        if ($errors !== []) {
            throw new ValidationException(new ValidationResult($errors));
        }

        return [$day, $hour === '' ? null : (int) $hour * 60, (int) $minutes];
    }

    private function assertRoom(int $userId, string $day, ?int $exceptId): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM planner_sessions WHERE user_id = ? AND day = ? AND id <> ?');
        $stmt->execute([$userId, $day, $exceptId ?? 0]);
        if ((int) $stmt->fetchColumn() >= self::MAX_PER_DAY) {
            $this->fail('day', 'A day can hold up to 20 planned sessions.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
