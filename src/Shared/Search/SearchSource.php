<?php

declare(strict_types=1);

namespace App\Shared\Search;

/**
 * Something a module can be searched for. The caller passes only boards the
 * user may see, so a source never decides about permissions itself. Archived
 * cards are returned too; the caller filters them.
 */
interface SearchSource
{
    /**
     * @param list<int> $boardIds boards the user may see
     * @return list<SearchHit>
     */
    public function search(array $boardIds, string $text, int $limit): array;
}
