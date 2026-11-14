<?php

declare(strict_types=1);

namespace App\Modules\Checklist\Infrastructure;

use App\Shared\Search\SearchHit;
use App\Shared\Search\SearchSource;
use PDO;

/** Checklist titles and items. */
final class PdoChecklistSearch implements SearchSource
{
    public function __construct(private PDO $db)
    {
    }

    public function search(array $boardIds, string $text, int $limit): array
    {
        $pattern = SearchHit::likePattern($text);
        $marks = implode(',', array_fill(0, count($boardIds), '?'));
        $stmt = $this->db->prepare("SELECT i.card_id, i.text FROM checklist_items i JOIN cards c ON c.id = i.card_id
                WHERE c.board_id IN ($marks) AND i.text LIKE ? ESCAPE '!'
            UNION ALL
            SELECT l.card_id, l.title FROM checklists l JOIN cards c ON c.id = l.card_id
                WHERE c.board_id IN ($marks) AND l.title LIKE ? ESCAPE '!'
            LIMIT " . max(1, $limit));
        $stmt->execute([...$boardIds, $pattern, ...$boardIds, $pattern]);

        return array_map(static fn (array $row) => new SearchHit((int) $row['card_id'], 'checklist', SearchHit::excerpt((string) $row['text'], $text)), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
