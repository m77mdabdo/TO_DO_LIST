<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Contract;

/**
 * The emoji people can react to a comment with. A short fixed set, kept by
 * key, so the database never holds arbitrary text and every reaction has a
 * name for screen readers.
 */
final class Reactions
{
    /** key => [emoji, English name (the translation key)] */
    public const ALL = [
        'thumbs' => ['👍', 'Thumbs up'],
        'heart'  => ['❤️', 'Heart'],
        'smile'  => ['😄', 'Smile'],
        'tada'   => ['🎉', 'Celebrate'],
        'eyes'   => ['👀', 'Looking'],
        'pray'   => ['🙏', 'Thanks'],
    ];

    public static function exists(string $key): bool
    {
        return isset(self::ALL[$key]);
    }
}
