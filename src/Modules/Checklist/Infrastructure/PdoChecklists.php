<?php

declare(strict_types=1);

namespace App\Modules\Checklist\Infrastructure;

use App\Database\OrderedRows;
use App\Modules\Checklist\Contract\Checklist;
use App\Modules\Checklist\Contract\ChecklistItem;
use App\Modules\Checklist\Domain\ChecklistRepository;
use App\Support\Clock;
use PDO;

final class PdoChecklists implements ChecklistRepository
{
    private const ITEM_COLUMNS = 'id, checklist_id, card_id, text, position, done_at, assignee_id, due_at';

    /** Columns updateItem() may write: an allow-list, never names from the request. */
    private const ITEM_UPDATABLE = ['text', 'assignee_id', 'due_at', 'done_at'];

    private OrderedRows $lists;
    private OrderedRows $items;

    public function __construct(private PDO $db, private Clock $clock)
    {
        $this->lists = new OrderedRows($db, 'checklists', 'card_id');
        $this->items = new OrderedRows($db, 'checklist_items', 'checklist_id');
    }

    public function forCard(int $cardId): array
    {
        $stmt = $this->db->prepare('SELECT id, card_id, title FROM checklists WHERE card_id = ? ORDER BY position, id');
        $stmt->execute([$cardId]);
        $rows = $stmt->fetchAll();

        if ($rows === []) {
            return [];
        }

        // Every item of the card in one query, grouped by checklist in a hash map.
        $items = $this->db->prepare('SELECT ' . self::ITEM_COLUMNS . ' FROM checklist_items WHERE card_id = ? ORDER BY checklist_id, position, id');
        $items->execute([$cardId]);
        $byList = [];
        foreach ($items->fetchAll() as $row) {
            $byList[(int) $row['checklist_id']][] = ChecklistItem::fromRow($row);
        }

        return array_map(
            static fn (array $row) => new Checklist((int) $row['id'], (int) $row['card_id'], (string) $row['title'], $byList[(int) $row['id']] ?? []),
            $rows,
        );
    }

    public function find(int $id): ?Checklist
    {
        $stmt = $this->db->prepare('SELECT id, card_id, title FROM checklists WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? new Checklist((int) $row['id'], (int) $row['card_id'], (string) $row['title']) : null;
    }

    public function countOnCard(int $cardId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM checklists WHERE card_id = ?');
        $stmt->execute([$cardId]);

        return (int) $stmt->fetchColumn();
    }

    public function create(int $cardId, string $title, int $createdBy): int
    {
        $this->db->prepare('INSERT INTO checklists (card_id, title, position, created_by, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$cardId, $title, $this->lists->positionAtEnd($cardId), $createdBy, $this->now()]);

        return (int) $this->db->lastInsertId();
    }

    public function rename(int $id, string $title): void
    {
        $this->db->prepare('UPDATE checklists SET title = ? WHERE id = ?')->execute([$title, $id]);
    }

    public function delete(int $id): void
    {
        // Items go too (ON DELETE CASCADE); deleted explicitly for SQLite without foreign keys on.
        $this->db->prepare('DELETE FROM checklist_items WHERE checklist_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM checklists WHERE id = ?')->execute([$id]);
    }

    public function findItem(int $id): ?ChecklistItem
    {
        $stmt = $this->db->prepare('SELECT ' . self::ITEM_COLUMNS . ' FROM checklist_items WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? ChecklistItem::fromRow($row) : null;
    }

    public function countItems(int $checklistId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM checklist_items WHERE checklist_id = ?');
        $stmt->execute([$checklistId]);

        return (int) $stmt->fetchColumn();
    }

    public function addItem(int $checklistId, int $cardId, string $text, int $createdBy): int
    {
        $this->db->prepare('INSERT INTO checklist_items (checklist_id, card_id, text, position, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$checklistId, $cardId, $text, $this->items->positionAtEnd($checklistId), $createdBy, $this->now()]);

        return (int) $this->db->lastInsertId();
    }

    public function updateItem(int $id, array $fields): void
    {
        $fields = array_intersect_key($fields, array_flip(self::ITEM_UPDATABLE));

        if ($fields === []) {
            return;
        }

        $sets = implode(', ', array_map(static fn (string $column) => "$column = ?", array_keys($fields)));
        $this->db->prepare("UPDATE checklist_items SET $sets WHERE id = ?")->execute([...array_values($fields), $id]);
    }

    public function moveItem(int $id, int $checklistId, ?int $afterId): void
    {
        $position = $this->items->positionAfter($checklistId, $afterId, $id);
        $this->db->prepare('UPDATE checklist_items SET checklist_id = ?, position = ? WHERE id = ?')->execute([$checklistId, $position, $id]);
    }

    public function deleteItem(int $id): void
    {
        $this->db->prepare('DELETE FROM checklist_items WHERE id = ?')->execute([$id]);
    }

    public function progressForCards(array $cardIds): array
    {
        if ($cardIds === []) {
            return [];
        }

        $marks = implode(', ', array_fill(0, count($cardIds), '?'));
        $stmt = $this->db->prepare("SELECT card_id, COUNT(*) AS total, COUNT(done_at) AS done
                                    FROM checklist_items WHERE card_id IN ($marks) GROUP BY card_id");
        $stmt->execute(array_values($cardIds));

        $progress = [];
        foreach ($stmt->fetchAll() as $row) {
            $progress[(int) $row['card_id']] = ['done' => (int) $row['done'], 'total' => (int) $row['total']];
        }

        return $progress;
    }

    public function itemsDueBetween(array $cardIds, string $from, string $to): array
    {
        if ($cardIds === []) {
            return [];
        }
        $marks = implode(', ', array_fill(0, count($cardIds), '?'));
        $stmt = $this->db->prepare("SELECT * FROM checklist_items WHERE card_id IN ($marks) AND due_at >= ? AND due_at <= ? ORDER BY due_at, id");
        $stmt->execute([...array_values($cardIds), $from . ' 00:00:00', $to . ' 23:59:59']);

        return array_map([ChecklistItem::class, 'fromRow'], $stmt->fetchAll());
    }

    public function openItemsAssignedTo(int $userId, int $limit = 200): array
    {
        $stmt = $this->db->prepare('SELECT i.* FROM checklist_items i JOIN cards c ON c.id = i.card_id
            WHERE i.assignee_id = ? AND i.done_at IS NULL AND c.archived_at IS NULL
            ORDER BY i.due_at IS NULL, i.due_at, i.id LIMIT ' . max(1, $limit));
        $stmt->execute([$userId]);

        return array_map([ChecklistItem::class, 'fromRow'], $stmt->fetchAll());
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
