<?php

declare(strict_types=1);

namespace App\Shared\Search;

/** A card that matched, and where: "card", "comment", "checklist" or "attachment". */
final class SearchHit
{
    public function __construct(
        public readonly int $cardId,
        public readonly string $where,
        public readonly string $excerpt = '',
    ) {
    }

    /** About $width characters of $text around the first match of $needle. */
    public static function excerpt(string $text, string $needle, int $width = 120): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $at = mb_stripos($text, $needle);
        if ($at === false || mb_strlen($text) <= $width) {
            return mb_substr($text, 0, $width);
        }
        $start = max(0, $at - intdiv($width - mb_strlen($needle), 2));

        return ($start > 0 ? '…' : '') . mb_substr($text, $start, $width) . ($start + $width < mb_strlen($text) ? '…' : '');
    }

    /** $text as a LIKE pattern, with % _ and the escape character "!" escaped (works on SQLite and MySQL). */
    public static function likePattern(string $text): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text) . '%';
    }
}
