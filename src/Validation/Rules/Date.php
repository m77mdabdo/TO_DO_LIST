<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\Rule;
use DateTimeImmutable;

/** Y-m-d, which is what <input type="date"> sends. */
final class Date implements Rule
{
    public function check(string $label, mixed $value, array $params): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

        return $date && $date->format('Y-m-d') === $value ? null : "$label must be a valid date.";
    }
}
