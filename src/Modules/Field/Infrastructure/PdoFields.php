<?php

declare(strict_types=1);

namespace App\Modules\Field\Infrastructure;

use App\Modules\Field\Contract\CustomField;
use App\Modules\Field\Domain\FieldRepository;
use App\Support\Clock;
use PDO;
use PDOException;

final class PdoFields implements FieldRepository
{
    private const COLUMNS = 'id, board_id, name, type, options, position, on_card';

    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function forBoard(int $boardId): array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM custom_fields WHERE board_id = ? ORDER BY position, id');
        $stmt->execute([$boardId]);

        return array_map(static fn (array $row) => CustomField::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $fieldId, int $boardId): ?CustomField
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM custom_fields WHERE id = ? AND board_id = ?');
        $stmt->execute([$fieldId, $boardId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? CustomField::fromRow($row) : null;
    }

    public function findByName(int $boardId, string $name): ?CustomField
    {
        foreach ($this->forBoard($boardId) as $field) {
            if (mb_strtolower($field->name) === mb_strtolower($name)) {
                return $field;
            }
        }

        return null;
    }

    public function create(int $boardId, string $name, string $type, array $options, bool $onCard): CustomField
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM custom_fields WHERE board_id = ?');
        $stmt->execute([$boardId]);
        $position = (int) $stmt->fetchColumn();

        $this->db->prepare('INSERT INTO custom_fields (board_id, name, type, options, position, on_card, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$boardId, $name, $type, json_encode($options, JSON_UNESCAPED_UNICODE), $position, $onCard ? 1 : 0, $this->now()]);

        return new CustomField((int) $this->db->lastInsertId(), $boardId, $name, $type, $options, $position, $onCard);
    }

    public function update(int $fieldId, string $name, array $options, bool $onCard): void
    {
        $this->db->prepare('UPDATE custom_fields SET name = ?, options = ?, on_card = ? WHERE id = ?')
            ->execute([$name, json_encode($options, JSON_UNESCAPED_UNICODE), $onCard ? 1 : 0, $fieldId]);
    }

    public function reorder(int $boardId, array $orderedIds): void
    {
        $stmt = $this->db->prepare('UPDATE custom_fields SET position = ? WHERE id = ? AND board_id = ?');
        foreach (array_values($orderedIds) as $i => $id) {
            $stmt->execute([$i + 1, $id, $boardId]);
        }
    }

    public function delete(int $fieldId): void
    {
        $this->db->prepare('DELETE FROM card_field_values WHERE field_id = ?')->execute([$fieldId]);
        $this->db->prepare('DELETE FROM custom_fields WHERE id = ?')->execute([$fieldId]);
    }

    public function valueCount(int $fieldId, ?string $value = null): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM card_field_values WHERE field_id = ?' . ($value !== null ? ' AND value = ?' : ''));
        $stmt->execute($value !== null ? [$fieldId, $value] : [$fieldId]);

        return (int) $stmt->fetchColumn();
    }

    public function valuesForCards(array $cardIds): array
    {
        if ($cardIds === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk(array_values($cardIds), 500) as $chunk) {
            $stmt = $this->db->prepare('SELECT card_id, field_id, value FROM card_field_values WHERE card_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$cardId, $fieldId, $value]) {
                $out[(int) $cardId][(int) $fieldId] = (string) $value;
            }
        }

        return $out;
    }

    public function setValue(int $cardId, int $fieldId, string $value): void
    {
        $update = $this->db->prepare('UPDATE card_field_values SET value = ?, updated_at = ? WHERE card_id = ? AND field_id = ?');
        $update->execute([$value, $this->now(), $cardId, $fieldId]);
        if ($update->rowCount() > 0) {
            return;
        }

        try {
            $this->db->prepare('INSERT INTO card_field_values (card_id, field_id, value, updated_at) VALUES (?, ?, ?, ?)')
                ->execute([$cardId, $fieldId, $value, $this->now()]);
        } catch (PDOException) {
            // The same value was written by a parallel request (MySQL reports 0 changed rows for an equal value).
            $update->execute([$value, $this->now(), $cardId, $fieldId]);
        }
    }

    public function clearValue(int $cardId, int $fieldId): void
    {
        $this->db->prepare('DELETE FROM card_field_values WHERE card_id = ? AND field_id = ?')->execute([$cardId, $fieldId]);
    }

    public function clearValuesNotIn(int $fieldId, array $keep): void
    {
        if ($keep === []) {
            $this->db->prepare('DELETE FROM card_field_values WHERE field_id = ?')->execute([$fieldId]);

            return;
        }
        $this->db->prepare('DELETE FROM card_field_values WHERE field_id = ? AND value NOT IN (' . implode(',', array_fill(0, count($keep), '?')) . ')')
            ->execute([$fieldId, ...array_values($keep)]);
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
