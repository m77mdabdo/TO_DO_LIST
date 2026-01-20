<?php

namespace Classes\Validation;

class Email implements Validator
{
    public function check(string $label, mixed $value, array $params = []): string|false
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? false : "$label must be a valid email address.";
    }
}
