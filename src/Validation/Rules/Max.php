<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\Rule;

final class Max implements Rule
{
    public function check(string $label, mixed $value, array $params): ?string
    {
        $max = (int) ($params[0] ?? 255);

        return mb_strlen((string) $value) <= $max ? null : ":label may not be longer than :param characters.";
    }
}
