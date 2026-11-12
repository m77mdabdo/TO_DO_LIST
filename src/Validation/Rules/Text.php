<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\Rule;

/** Real words, not just a number. */
final class Text implements Rule
{
    public function check(string $label, mixed $value, array $params): ?string
    {
        return is_string($value) && !is_numeric($value) ? null : ":label must be text, not just a number.";
    }
}
