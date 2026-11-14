<?php

declare(strict_types=1);

namespace App\Shared\Search;

/**
 * Every module's search source. A card that matches in several places is
 * listed once, under the first place found (title before comments, and so on).
 */
final class SearchSources
{
    /** @var list<SearchSource> */
    private array $sources = [];

    public function add(SearchSource $source): void
    {
        $this->sources[] = $source;
    }

    /**
     * @param list<int> $boardIds
     * @return array<int, SearchHit> card id => hit, at most $limit
     */
    public function search(array $boardIds, string $text, int $limit = 200): array
    {
        $hits = [];
        if ($boardIds === [] || mb_strlen($text) < 2) {
            return $hits;
        }
        foreach ($this->sources as $source) {
            foreach ($source->search($boardIds, $text, $limit) as $hit) {
                $hits[$hit->cardId] ??= $hit;
                if (count($hits) >= $limit) {
                    return $hits;
                }
            }
        }

        return $hits;
    }
}
