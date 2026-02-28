<?php

declare(strict_types=1);

namespace App\View;

final class Html
{
    /** Everything printed in a template goes through here. */
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** A stable color (cat-0 … cat-7) for a list name. */
    public static function categoryClass(?string $category): string
    {
        return 'cat-' . (crc32(mb_strtolower((string) $category)) % 8);
    }
}
