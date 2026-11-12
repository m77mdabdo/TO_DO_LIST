<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\Rule;

/** in:low,normal,high */
final class In implements Rule
{
    public function check(string $label, mixed $value, array $params): ?string
    {
        return in_array($value, $params, true) ? null : ":label has an invalid value.";
    }
}
