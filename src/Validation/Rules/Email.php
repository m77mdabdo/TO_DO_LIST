<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\Rule;

final class Email implements Rule
{
    public function check(string $label, mixed $value, array $params): ?string
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? null : "$label must be a valid email address.";
    }
}
