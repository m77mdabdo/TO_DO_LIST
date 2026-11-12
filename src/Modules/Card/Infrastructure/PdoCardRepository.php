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
    private const COLUMNS = 'id, public_id, board_id, list_id, title, description, position, due_at, completed_at, archived_at, created_at';

    /** Columns update() may write: an allow-list, never names from the request. */
    private const UPDATABLE = ['title', 'description', 'due_at', 'completed_at', 'list_id', 'position'];

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

    public function archivedCards(int $boardId): array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM cards WHERE board_id = ? AND archived_at IS NOT NULL ORDER BY archived_at DESC, id DESC');
        $stmt->execute([$boardId]);

        return array_map([CardSummary::class, 'fromRow'], $stmt->fetchAll());
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
