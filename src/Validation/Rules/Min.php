<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\Rule;

/** min:3 -> at least 3 characters (an Arabic letter counts as one). */
final class Min implements Rule
{
    public function check(string $label, mixed $value, array $params): ?string
    {
        $min = (int) ($params[0] ?? 1);

        return mb_strlen((string) $value) >= $min ? null : "$label must be at least $min characters.";
    }
}
