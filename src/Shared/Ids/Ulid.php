<?php

declare(strict_types=1);

namespace App\Shared\Ids;

/**
 * Public, non-guessable ids for URLs (boards, cards, ...).
 *
 * 26 chars of Crockford base32: 48 bits of milliseconds then 80 random
 * bits. They sort by creation time, carry no sequence an attacker can
 * walk (unlike /boards/1, /boards/2), and need no database round trip.
 * They are never a substitute for an access check.
 */
final class Ulid
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const PATTERN = '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/';

    public static function generate(?int $milliseconds = null): string
    {
        $time = $milliseconds ?? (int) floor(microtime(true) * 1000);
        $timePart = '';

        for ($i = 0; $i < 10; $i++) {
            $timePart = self::ALPHABET[$time & 31] . $timePart;
            $time >>= 5;
        }

        $randomPart = '';
        foreach (str_split(random_bytes(16)) as $byte) {
            $randomPart .= self::ALPHABET[ord($byte) & 31];
        }

        return $timePart . $randomPart;
    }

    public static function isValid(string $value): bool
    {
        return (bool) preg_match(self::PATTERN, $value);
    }
}
