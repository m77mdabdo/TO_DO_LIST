<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Domain;

/**
 * Finds the people @mentioned in a comment.
 *
 * A person can be written as "@Full Name", or as "@FirstName" when nobody
 * else on the board shares that first name. Matching ignores case and needs
 * a boundary after the name, so "@Mona" does not match "@Monalisa".
 * Cost: one scan of the text per candidate name, O(people * length).
 */
final class Mentions
{
    /**
     * @param array<int, string> $people user id => display name
     * @return array<int, list<string>> user id => the spellings that may mention them
     */
    public static function handles(array $people): array
    {
        $firstNames = [];
        foreach ($people as $id => $name) {
            $first = mb_strtolower(explode(' ', trim($name))[0]);
            $firstNames[$first][] = $id;
        }

        $handles = [];
        foreach ($people as $id => $name) {
            $name = trim($name);
            $handles[$id] = [$name];
            $first = explode(' ', $name)[0];
            if ($first !== $name && count($firstNames[mb_strtolower($first)]) === 1) {
                $handles[$id][] = $first;
            }
        }

        return $handles;
    }

    /**
     * @param array<int, string> $people user id => display name
     * @return list<int> ids of the people mentioned in $text
     */
    public static function find(string $text, array $people): array
    {
        if (!str_contains($text, '@')) {
            return [];
        }

        $found = [];
        foreach (self::handles($people) as $id => $spellings) {
            foreach ($spellings as $spelling) {
                if (preg_match('/@' . preg_quote($spelling, '/') . '(?![\\p{L}\\p{N}_])/iu', $text)) {
                    $found[] = $id;
                    break;
                }
            }
        }

        return $found;
    }
}
