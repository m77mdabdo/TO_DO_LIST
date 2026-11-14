<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure;

use App\Shared\Search\SearchHit;
use App\Shared\Search\SearchSource;
use PDO;

/** Card titles and descriptions. */
final class PdoCardSearch implements SearchSource
{
    public function __construct(private PDO $db)
    {
    }

    public function search(array $boardIds, string $text, int $limit): array
    {
        $pattern = SearchHit::likePattern($text);
        // board_id IN (...) uses idx_cards_board; the text match runs on those boards' cards only.
        $stmt = $this->db->prepare('SELECT id, title, description FROM cards WHERE board_id IN (' . implode(',', array_fill(0, count($boardIds), '?')) . ")
            AND (title LIKE ? ESCAPE '!' OR description LIKE ? ESCAPE '!') ORDER BY updated_at DESC, id DESC LIMIT " . max(1, $limit));
        $stmt->execute([...$boardIds, $pattern, $pattern]);

        return array_map(static fn (array $row) => mb_stripos((string) $row['title'], $text) !== false
            ? new SearchHit((int) $row['id'], 'card')
            : new SearchHit((int) $row['id'], 'description', SearchHit::excerpt((string) $row['description'], $text)), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
