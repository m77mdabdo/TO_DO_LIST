<?php

namespace Classes\Validation;

// Min:3 -> at least 3 characters (Arabic letters count as one each)
class Min implements Validator
{
    public function check(string $label, mixed $value, array $params = []): string|false
    {
        $min = (int) ($params[0] ?? 1);

        return mb_strlen((string) $value) < $min ? "$label must be at least $min characters." : false;
    }
}
