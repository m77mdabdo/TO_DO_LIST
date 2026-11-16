<?php

declare(strict_types=1);

namespace App\Modules\Personal\Application;

use App\Core\Config;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Card\Contract\CardQueries;
use App\Support\Clock;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * A private calendar link (iCal) per person, for Google Calendar, Outlook or
 * Apple Calendar to subscribe to. The link carries a random secret; only
 * its hash is stored, so it is shown once and can be replaced or turned off
 * at any time. Every fetch rebuilds the feed with what the person may see
 * right now: due dates of cards given to them and their planned work time.
 */
final class CalendarFeed
{
    public const PAST_DAYS = 30;
    public const AHEAD_DAYS = 180;

    public function __construct(
        private PDO $db,
        private CardQueries $cards,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private Planner $planner,
        private Config $config,
        private Clock $clock,
    ) {
    }

    /** @return array{created_at: string, last_used_at: ?string}|null */
    public function status(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT created_at, last_used_at FROM calendar_feeds WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : ['created_at' => (string) $row['created_at'], 'last_used_at' => $row['last_used_at'] !== null ? (string) $row['last_used_at'] : null];
    }

    /** Makes a new link (the old one stops working) and returns its secret, which is shown once. */
    public function renew(int $userId): string
    {
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->prepare('DELETE FROM calendar_feeds WHERE user_id = ?')->execute([$userId]);
        $this->db->prepare('INSERT INTO calendar_feeds (user_id, token_hash, created_at) VALUES (?, ?, ?)')
            ->execute([$userId, hash('sha256', $secret), $this->clock->now()->format('Y-m-d H:i:s')]);

        return $secret;
    }

    public function revoke(int $userId): void
    {
        $this->db->prepare('DELETE FROM calendar_feeds WHERE user_id = ?')->execute([$userId]);
    }

    public function url(string $secret): string
    {
        return rtrim((string) $this->config->get('url', ''), '/') . '/calendar/' . $secret . '.ics';
    }

    /** The owner of a link, or null for an unknown or turned-off one. Notes when it was last fetched. */
    public function owner(string $secret): ?int
    {
        $stmt = $this->db->prepare('SELECT user_id FROM calendar_feeds WHERE token_hash = ?');
        $stmt->execute([hash('sha256', $secret)]);
        $userId = $stmt->fetchColumn();
        if ($userId === false) {
            return null;
        }
        $this->db->prepare('UPDATE calendar_feeds SET last_used_at = ? WHERE user_id = ?')->execute([$this->clock->now()->format('Y-m-d H:i:s'), (int) $userId]);

        return (int) $userId;
    }

    /** The iCalendar text for one person. Event ids stay the same between fetches, so calendars update instead of duplicating. */
    public function render(int $userId, string $dueLabel, string $planLabel): string
    {
        $now = $this->clock->now();
        $from = $now->modify('-' . self::PAST_DAYS . ' days')->format('Y-m-d');
        $to = $now->modify('+' . self::AHEAD_DAYS . ' days')->format('Y-m-d');
        $host = (string) (parse_url((string) $this->config->get('url', ''), PHP_URL_HOST) ?: 'todo-board');
        $base = rtrim((string) $this->config->get('url', ''), '/');
        $stamp = $now->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');

        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Todo Board//Calendar//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::text($dueLabel . ' · ' . $planLabel), 'REFRESH-INTERVAL;VALUE=DURATION:PT1H', 'X-PUBLISHED-TTL:PT1H'];

        $boards = [];
        foreach ($this->cards->openCardsAssignedTo($userId, 1000) as $card) {
            $due = $card->dueDate();
            if ($due === null || $due < $from || $due > $to || $card->archived) {
                continue;
            }
            $boards[$card->boardId] ??= $this->boards->find($card->boardId);
            $board = $boards[$card->boardId];
            if ($board === null || $board->archived || !$this->gate->can($userId, $board->id, Permission::ViewBoard)) {
                continue;
            }
            $day = new DateTimeImmutable($due);
            array_push($lines, 'BEGIN:VEVENT', 'UID:card-' . $card->publicId . '-due@' . $host, 'DTSTAMP:' . $stamp,
                'DTSTART;VALUE=DATE:' . $day->format('Ymd'), 'DTEND;VALUE=DATE:' . $day->modify('+1 day')->format('Ymd'),
                'SUMMARY:' . self::text($dueLabel . ': ' . $card->title), 'DESCRIPTION:' . self::text($board->name),
                'URL:' . $base . '/cards/' . $card->publicId, 'TRANSP:TRANSPARENT', 'END:VEVENT');
        }

        $zone = new DateTimeZone(date_default_timezone_get());
        foreach ($this->planner->between($userId, $from, $to) as $session) {
            $card = $session['card'];
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:plan-' . $session['id'] . '@' . $host;
            $lines[] = 'DTSTAMP:' . $stamp;
            $day = new DateTimeImmutable($session['day'], $zone);
            if ($session['hour'] === null) {
                $lines[] = 'DTSTART;VALUE=DATE:' . $day->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:' . $day->modify('+1 day')->format('Ymd');
            } else {
                $start = $day->setTime($session['hour'], 0)->setTimezone(new DateTimeZone('UTC'));
                $lines[] = 'DTSTART:' . $start->format('Ymd\THis\Z');
                $lines[] = 'DTEND:' . $start->modify('+' . $session['minutes'] . ' minutes')->format('Ymd\THis\Z');
            }
            $lines[] = 'SUMMARY:' . self::text($planLabel . ': ' . $card->title);
            $lines[] = 'DESCRIPTION:' . self::text($session['board']->name . ($card->dueDate() !== null ? ' · ' . $dueLabel . ' ' . $card->dueDate() : ''));
            $lines[] = 'URL:' . $base . '/cards/' . $card->publicId;
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines)) . "\r\n";
    }

    /** Escapes a TEXT value (RFC 5545 3.3.11). */
    private static function text(string $value): string
    {
        $value = (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $value);

        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', ''], $value);
    }

    /** Folds a line at 75 bytes without splitting a UTF-8 character. */
    private static function fold(string $line): string
    {
        $out = '';
        $limit = 75;
        while (strlen($line) > $limit) {
            $cut = $limit;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut) . "\r\n ";
            $line = substr($line, $cut);
            $limit = 74;
        }

        return $out . $line;
    }
}
