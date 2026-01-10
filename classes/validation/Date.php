<?php

namespace Classes\Validation;

use DateTime;

// expects Y-m-d, which is what <input type="date"> sends
class Date implements Validator
{
    public function check(string $label, mixed $value, array $params = []): string|false
    {
        $date = DateTime::createFromFormat('!Y-m-d', (string) $value);

        return $date && $date->format('Y-m-d') === $value ? false : "$label must be a valid date.";
    }
}
