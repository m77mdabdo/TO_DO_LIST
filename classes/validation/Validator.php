<?php

namespace Classes\Validation;

interface Validator
{
    /**
     * Return an error message, or false when the value passes.
     */
    public function check(string $label, mixed $value, array $params = []): string|false;
}
