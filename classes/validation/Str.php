<?php

namespace Classes\Validation;

class Str implements Validator
{
    public function check(string $label, mixed $value, array $params = []): string|false
    {
        if (!is_string($value) || is_numeric($value)) {
            return "$label must be text, not just a number.";
        }

        return false;
    }
}
