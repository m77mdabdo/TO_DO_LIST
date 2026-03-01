<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\Rule;

final class Required implements Rule
{
    public function check(string $label, mixed $value, array $params): ?string
    {
        return is_string($value) && trim($value) !== '' ? null : "$label is required.";
    }
}
