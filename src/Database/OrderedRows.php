<?php

declare(strict_types=1);

namespace App\Database;

use App\Shared\Ordering\FractionalIndex;
use InvalidArgumentException;
use PDO;

/**
 * Ordering for rows kept in a fractional-index "position" column inside a
 * scope (the lists of a board, the cards of a list).
 *
 * Placing a row needs its new neighbours. Both come from the
 * (scope, position) index: the row it goes after by primary key, the next
 * one with a single "position > ? ORDER BY position LIMIT 1" seek. So a
 * move is O(log n) reads and one write. When keys get too long, the scope
 * is renumbered once in O(n), which keeps every later move cheap.
 *
 * Table and column names come from code, never from the request.
 */
final class OrderedRows
{
    public function __construct(
        private PDO $db,
        private string $table,
        private string $scopeColumn,
    ) {
    }

    /**
     * Position for a row placed right after $afterId (or first when null).
     *
     * @throws InvalidArgumentException when $afterId isn't in that scope
     */
    public function positionAfter(int $scope, ?int $afterId, ?int $movingId = null): string
    {
        $key = $this->compute($scope, $afterId, $movingId);

        if (strlen($key) > FractionalIndex::NEEDS_REBALANCE) {
            $this->rebalance($scope);
            $key = $this->compute($scope, $afterId, $movingId);
        }

        return $key;
    }

    /** Position after the last row of the scope (append). */
    public function positionAtEnd(int $scope): string
    {
        $stmt = $this->db->prepare("SELECT position FROM {$this->table} WHERE {$this->scopeColumn} = ? ORDER BY position DESC LIMIT 1");
        $stmt->execute([$scope]);
        $last = $stmt->fetchColumn();

        return FractionalIndex::between($last === false ? null : (string) $last, null);
    }

    /** Renumbers the whole scope with short, evenly spaced keys: O(n). */
    public function rebalance(int $scope): void
    {
        $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE {$this->scopeColumn} = ? ORDER BY position, id");
        $stmt->execute([$scope]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $update = $this->db->prepare("UPDATE {$this->table} SET position = ? WHERE id = ?");
        foreach (FractionalIndex::spread(count($ids)) as $i => $key) {
            $update->execute([$key, $ids[$i]]);
        }
    }

    private function compute(int $scope, ?int $afterId, ?int $movingId): string
    {
        $exclude = $movingId !== null ? ' AND id <> ' . $movingId : '';

        if ($afterId === null) {
            $stmt = $this->db->prepare("SELECT position FROM {$this->table} WHERE {$this->scopeColumn} = ?$exclude ORDER BY position LIMIT 1");
            $stmt->execute([$scope]);
            $first = $stmt->fetchColumn();

            return FractionalIndex::between(null, $first === false ? null : (string) $first);
        }

        $stmt = $this->db->prepare("SELECT position FROM {$this->table} WHERE id = ? AND {$this->scopeColumn} = ?");
        $stmt->execute([$afterId, $scope]);
        $after = $stmt->fetchColumn();

        if ($after === false || $afterId === $movingId) {
            throw new InvalidArgumentException('The row to place after is not in this scope.');
        }

        $stmt = $this->db->prepare("SELECT position FROM {$this->table} WHERE {$this->scopeColumn} = ? AND position > ?$exclude ORDER BY position LIMIT 1");
        $stmt->execute([$scope, $after]);
        $next = $stmt->fetchColumn();

        return FractionalIndex::between((string) $after, $next === false ? null : (string) $next);
    }
}
