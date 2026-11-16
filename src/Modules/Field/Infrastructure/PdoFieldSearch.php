<?php

declare(strict_types=1);

namespace App\Modules\Field\Infrastructure;

use App\Modules\Field\Contract\CustomField;
use App\Shared\Search\SearchHit;
use App\Shared\Search\SearchSource;
use PDO;

/**
 * Custom field values: text and numbers by their text, choices by the
 * choice's name. The fields of the boards are read first (a few per board),
 * so the value query only asks for what can match.
 */
final class PdoFieldSearch implements SearchSource
{
    public function __construct(private PDO $db)
    {
    }

    public function search(array $boardIds, string $text, int $limit): array
    {
        $stmt = $this->db->prepare('SELECT id, board_id, name, type, options, position, on_card FROM custom_fields WHERE board_id IN ('
            . implode(',', array_fill(0, count($boardIds), '?')) . ") AND type IN ('text', 'number', 'select')");
        $stmt->execute($boardIds);

        $fields = [];
        $where = [];
        $args = [];
        $needle = mb_strtolower($text);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $field = CustomField::fromRow($row);
            $fields[$field->id] = $field;
            if ($field->type === 'select') {
                $ids = array_values(array_map(static fn ($o) => $o['id'], array_filter($field->options, static fn ($o) => str_contains(mb_strtolower($o['name']), $needle))));
                if ($ids) {
                    $where[] = '(field_id = ? AND value IN (' . implode(',', array_fill(0, count($ids), '?')) . '))';
                    array_push($args, $field->id, ...$ids);
                }
            } else {
                $where[] = "(field_id = ? AND value LIKE ? ESCAPE '!')";
                array_push($args, $field->id, SearchHit::likePattern($text));
            }
        }
        if ($where === []) {
            return [];
        }

        $values = $this->db->prepare('SELECT card_id, field_id, value FROM card_field_values WHERE ' . implode(' OR ', $where) . ' ORDER BY card_id DESC LIMIT ' . max(1, $limit));
        $values->execute($args);

        return array_map(static function (array $row) use ($fields, $text): SearchHit {
            $field = $fields[(int) $row['field_id']];

            return new SearchHit((int) $row['card_id'], 'field', $field->name . ': ' . SearchHit::excerpt($field->display((string) $row['value']), $text));
        }, $values->fetchAll(PDO::FETCH_ASSOC));
    }
}
