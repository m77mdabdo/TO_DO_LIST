<?php

declare(strict_types=1);

namespace App\Shared\Ordering;

use InvalidArgumentException;

/**
 * Ordering keys for drag and drop.
 *
 * Each item stores a string key; items sort by plain byte comparison.
 * Moving an item only computes a new key between its two new neighbours
 * and writes that one row: O(key length), instead of renumbering every
 * item after it (O(n) writes).
 *
 * Keys are base-62 fractions ("0.V" ~ one half) with no trailing "0".
 * Repeated inserts at the same spot make keys longer, so callers rebalance
 * a list with spread() once a key passes NEEDS_REBALANCE characters,
 * which is rare in practice.
 */
final class FractionalIndex
{
    public const DIGITS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    public const NEEDS_REBALANCE = 48;

    /** A key strictly between $before and $after; null means "no neighbour on that side". */
    public static function between(?string $before, ?string $after): string
    {
        $before = $before ?? '';
        self::assertValid($before, allowEmpty: true);

        if ($after !== null) {
            self::assertValid($after);

            if (strcmp($before, $after) >= 0) {
                throw new InvalidArgumentException("Key [$before] must sort before [$after].");
            }
        }

        if ($after === null) {
            return $before === '' ? 'V' : self::after($before);
        }

        if ($before === '') {
            return self::before($after);
        }

        return self::midpoint($before, $after);
    }

    /**
     * n keys spread evenly over the whole range, all the same short length.
     * Used to rebalance a list in one O(n) pass.
     *
     * @return list<string>
     */
    public static function spread(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $base = strlen(self::DIGITS);
        $width = 1;
        while ($base ** $width <= $count) {
            $width++;
        }

        $space = $base ** $width;
        $step = intdiv($space, $count + 1);
        $keys = [];

        for ($i = 1; $i <= $count; $i++) {
            $value = $i * $step;
            $key = '';
            for ($d = 0; $d < $width; $d++) {
                $key = self::DIGITS[$value % $base] . $key;
                $value = intdiv($value, $base);
            }
            $keys[] = rtrim($key, '0');
        }

        return $keys;
    }

    /** Shortest key greater than $key: bump the first digit that can still grow. */
    private static function after(string $key): string
    {
        $last = strlen(self::DIGITS) - 1;

        for ($i = 0, $n = strlen($key); $i < $n; $i++) {
            $digit = strpos(self::DIGITS, $key[$i]);

            if ($digit < $last) {
                return substr($key, 0, $i) . self::DIGITS[$digit + 1];
            }
        }

        return $key . 'V';
    }

    /** Shortest key smaller than $key: lower the first digit that can still shrink. */
    private static function before(string $key): string
    {
        for ($i = 0, $n = strlen($key); $i < $n; $i++) {
            $digit = strpos(self::DIGITS, $key[$i]);

            // Lowering to "0" would leave a trailing zero, so the digit must be at least 2.
            if ($digit >= 2) {
                return substr($key, 0, $i) . self::DIGITS[$digit - 1];
            }
        }

        return self::midpoint('', $key);
    }

    /** Midpoint of two fractions given as digit strings, $a < $b (or $b null = 1.0). */
    private static function midpoint(string $a, ?string $b): string
    {
        $zero = self::DIGITS[0];

        if ($b !== null) {
            $n = 0;
            while (($a[$n] ?? $zero) === ($b[$n] ?? null)) {
                $n++;
            }

            if ($n > 0) {
                return substr($b, 0, $n) . self::midpoint(substr($a, $n), substr($b, $n));
            }
        }

        $digitA = $a !== '' ? strpos(self::DIGITS, $a[0]) : 0;
        $digitB = $b !== null ? strpos(self::DIGITS, $b[0]) : strlen(self::DIGITS);

        if ($digitB - $digitA > 1) {
            return self::DIGITS[intdiv($digitA + $digitB + 1, 2)];
        }

        if ($b !== null && strlen($b) > 1) {
            return $b[0];
        }

        return self::DIGITS[$digitA] . self::midpoint(substr($a, 1), null);
    }

    private static function assertValid(string $key, bool $allowEmpty = false): void
    {
        if ($key === '' && $allowEmpty) {
            return;
        }

        if ($key === '' || strspn($key, self::DIGITS) !== strlen($key) || str_ends_with($key, '0')) {
            throw new InvalidArgumentException("Invalid ordering key [$key].");
        }
    }
}
