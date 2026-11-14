<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Infrastructure;

use App\Shared\Search\SearchHit;
use App\Shared\Search\SearchSource;
use PDO;

/** Comment text, newest first. */
final class PdoCommentSearch implements SearchSource
{
    public function __construct(private PDO $db)
    {
    }

    public function search(array $boardIds, string $text, int $limit): array
    {
        $stmt = $this->db->prepare('SELECT card_id, body FROM comments WHERE board_id IN (' . implode(',', array_fill(0, count($boardIds), '?')) . ")
            AND body LIKE ? ESCAPE '!' ORDER BY id DESC LIMIT " . max(1, $limit));
        $stmt->execute([...$boardIds, SearchHit::likePattern($text)]);

        return array_map(static fn (array $row) => new SearchHit((int) $row['card_id'], 'comment', SearchHit::excerpt((string) $row['body'], $text)), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
