<?php

namespace Classes\Validation;

class Max implements Validator
{
    public function check(string $label, mixed $value, array $params = []): string|false
    {
        $max = (int) ($params[0] ?? 255);

        return mb_strlen((string) $value) > $max ? "$label may not be longer than $max characters." : false;
    }
}
