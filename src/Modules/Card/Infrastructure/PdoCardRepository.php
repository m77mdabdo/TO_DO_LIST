<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure;

use App\Database\OrderedRows;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Card\Domain\CardRepository;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use PDO;

final class PdoCardRepository implements CardRepository
{
    private const COLUMNS = 'id, public_id, board_id, list_id, title, description, position, start_at, due_at, completed_at, archived_at, created_at';

    /** Columns update() may write: an allow-list, never names from the request. */
    private const UPDATABLE = ['title', 'description', 'start_at', 'due_at', 'completed_at', 'list_id', 'position'];

    private OrderedRows $order;

    public function __construct(private PDO $db, private Clock $clock)
    {
        $this->order = new OrderedRows($db, 'cards', 'list_id');
    }

    public function create(int $boardId, int $listId, string $title, string $position, int $createdBy, array $extra = []): CardSummary
    {
        $now = $this->now();
        $created = $extra['created_at'] ?? $now;

        $this->db->prepare('INSERT INTO cards (public_id, board_id, list_id, title, description, position, due_at, completed_at, created_by, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                Ulid::generate(), $boardId, $listId, $title, $extra['description'] ?? null, $position,
                $extra['due_at'] ?? null, $extra['completed_at'] ?? null, $createdBy, $created, $now,
            ]);

        return $this->find((int) $this->db->lastInsertId());
    }

    public function find(int $id): ?CardSummary
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM cards WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? CardSummary::fromRow($row) : null;
    }

    public function findByPublicId(string $publicId): ?CardSummary
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM cards WHERE public_id = ?');
        $stmt->execute([$publicId]);
        $row = $stmt->fetch();

        return $row ? CardSummary::fromRow($row) : null;
    }

    public function update(int $id, array $fields): void
    {
        $fields = array_intersect_key($fields, array_flip(self::UPDATABLE));

        if ($fields === []) {
            return;
        }

        $sets = implode(', ', array_map(static fn (string $column) => "$column = ?", array_keys($fields)));
        $this->db->prepare("UPDATE cards SET $sets, updated_at = ? WHERE id = ?")
            ->execute([...array_values($fields), $this->now(), $id]);
    }

    public function setArchived(int $id, bool $archived): void
    {
        $this->db->prepare('UPDATE cards SET archived_at = ?, updated_at = ? WHERE id = ?')
            ->execute([$archived ? $this->now() : null, $this->now(), $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM cards WHERE id = ?')->execute([$id]);
    }

    public function openCardsByList(int $boardId): array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM cards WHERE board_id = ? AND archived_at IS NULL ORDER BY list_id, position, id');
        $stmt->execute([$boardId]);

        $byList = [];
        foreach ($stmt->fetchAll() as $row) {
            $byList[(int) $row['list_id']][] = CardSummary::fromRow($row);
        }

        return $byList;
    }

    public function findMany(array $ids): array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM cards WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);
        $cards = [];
        foreach ($stmt->fetchAll() as $row) {
            $card = CardSummary::fromRow($row);
            $cards[$card->id] = $card;
        }

        return $cards;
    }

    public function scheduledBetween(array $boardIds, string $from, string $to): array
    {
        $marks = implode(',', array_fill(0, count($boardIds), '?'));
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . " FROM cards WHERE board_id IN ($marks) AND archived_at IS NULL
            AND ((due_at >= ? AND due_at <= ?) OR (start_at >= ? AND start_at <= ?)) ORDER BY COALESCE(due_at, start_at), id");
        $stmt->execute([...$boardIds, $from . ' 00:00:00', $to . ' 23:59:59', $from . ' 00:00:00', $to . ' 23:59:59']);

        return array_map([CardSummary::class, 'fromRow'], $stmt->fetchAll());
    }

    public function search(array $boardIds, string $text, int $limit): array
    {
        // LIKE wildcards in the user's text are escaped with "!" (portable across SQLite and MySQL).
        $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text) . '%';
        $marks = implode(', ', array_fill(0, count($boardIds), '?'));
        $limit = (int) $limit;

        // board_id IN (...) uses idx_cards_board; the text match then runs on those boards' cards only.
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . " FROM cards
            WHERE board_id IN ($marks) AND archived_at IS NULL
              AND (title LIKE ? ESCAPE '!' OR description LIKE ? ESCAPE '!')
            ORDER BY updated_at DESC, id DESC LIMIT $limit");
        $stmt->execute([...array_values($boardIds), $pattern, $pattern]);

        return array_map([CardSummary::class, 'fromRow'], $stmt->fetchAll());
    }

    public function dueBetween(string $from, string $to): array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM cards
            WHERE due_at >= ? AND due_at <= ? AND completed_at IS NULL AND archived_at IS NULL ORDER BY due_at, id');
        $stmt->execute([$from . ' 00:00:00', $to . ' 23:59:59']);

        return array_map([CardSummary::class, 'fromRow'], $stmt->fetchAll());
    }

    public function archivedCards(int $boardId): array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM cards WHERE board_id = ? AND archived_at IS NOT NULL ORDER BY archived_at DESC, id DESC');
        $stmt->execute([$boardId]);

        return array_map([CardSummary::class, 'fromRow'], $stmt->fetchAll());
    }

    public function membersOf(int $cardId): array
    {
        return $this->membersForCards([$cardId])[$cardId] ?? [];
    }

    public function membersForCards(array $cardIds): array
    {
        if ($cardIds === []) {
            return [];
        }

        // One query for any number of cards; grouped in a hash map (no N+1).
        $marks = implode(', ', array_fill(0, count($cardIds), '?'));
        $stmt = $this->db->prepare("SELECT card_id, user_id FROM card_members WHERE card_id IN ($marks) ORDER BY created_at, user_id");
        $stmt->execute(array_values($cardIds));

        $byCard = [];
        foreach ($stmt->fetchAll() as $row) {
            $byCard[(int) $row['card_id']][] = (int) $row['user_id'];
        }

        return $byCard;
    }

    public function addMember(int $cardId, int $userId): bool
    {
        $exists = $this->db->prepare('SELECT 1 FROM card_members WHERE card_id = ? AND user_id = ?');
        $exists->execute([$cardId, $userId]);
        if ($exists->fetchColumn()) {
            return false;
        }

        $this->db->prepare('INSERT INTO card_members (card_id, user_id, created_at) VALUES (?, ?, ?)')
            ->execute([$cardId, $userId, $this->now()]);

        return true;
    }

    public function removeMember(int $cardId, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM card_members WHERE card_id = ? AND user_id = ?');
        $stmt->execute([$cardId, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function positionAfter(int $listId, ?int $afterCardId, ?int $movingId = null): string
    {
        return $this->order->positionAfter($listId, $afterCardId, $movingId);
    }

    public function positionAtEnd(int $listId): string
    {
        return $this->order->positionAtEnd($listId);
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
