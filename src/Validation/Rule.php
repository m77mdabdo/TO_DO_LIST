<?php

declare(strict_types=1);

namespace App\Validation;

interface Rule
{
    /**
     * @param list<string> $params values written after the colon, e.g. "Max:150" gives ["150"]
     * @return string|null an error message, or null when the value passes.
     *                     Write it in English with :label and :param placeholders;
     *                     the validator translates it and fills them in.
     */
    public function check(string $label, mixed $value, array $params): ?string;
}
