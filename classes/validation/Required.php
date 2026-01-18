<?php

namespace Classes\Validation;

class Required implements Validator
{
    public function check(string $label, mixed $value, array $params = []): string|false
    {
        return trim((string) $value) === '' ? "$label is required." : false;
    }
}
