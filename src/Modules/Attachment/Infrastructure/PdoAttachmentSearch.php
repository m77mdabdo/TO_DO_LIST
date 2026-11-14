<?php

declare(strict_types=1);

namespace App\Modules\Attachment\Infrastructure;

use App\Shared\Search\SearchHit;
use App\Shared\Search\SearchSource;
use PDO;

/** File names, link titles and link addresses. */
final class PdoAttachmentSearch implements SearchSource
{
    public function __construct(private PDO $db)
    {
    }

    public function search(array $boardIds, string $text, int $limit): array
    {
        $pattern = SearchHit::likePattern($text);
        $stmt = $this->db->prepare('SELECT card_id, name FROM attachments WHERE board_id IN (' . implode(',', array_fill(0, count($boardIds), '?')) . ")
            AND (name LIKE ? ESCAPE '!' OR url LIKE ? ESCAPE '!') ORDER BY id DESC LIMIT " . max(1, $limit));
        $stmt->execute([...$boardIds, $pattern, $pattern]);

        return array_map(static fn (array $row) => new SearchHit((int) $row['card_id'], 'attachment', (string) $row['name']), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
