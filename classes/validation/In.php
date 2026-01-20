<?php

namespace Classes\Validation;

// In:low,normal,high
class In implements Validator
{
    public function check(string $label, mixed $value, array $params = []): string|false
    {
        return in_array($value, $params, true) ? false : "$label has an invalid value.";
    }
}
