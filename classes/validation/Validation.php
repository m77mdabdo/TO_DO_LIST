<?php

namespace Classes\Validation;

use InvalidArgumentException;

/*
 | Usage:
 |   $v = new Validation();
 |   $v->validate('title', $title, ['Required', 'Str', 'Min:2', 'Max:150'], 'Title');
 |   if ($v->fails()) ...
 |
 | Add a new rule by dropping a class that implements Validator in this folder.
 | Fields that are empty skip every rule except Required.
 */
class Validation
{
    private array $errors = [];

    public function validate(string $key, mixed $value, array $rules, ?string $label = null): static
    {
        $label ??= ucfirst($key);
        $isEmpty = trim((string) $value) === '';

        foreach ($rules as $rule) {
            [$name, $params] = $this->parse($rule);

            if ($isEmpty && $name !== 'Required') {
                continue;
            }

            $class = __NAMESPACE__ . '\\' . $name;

            if (!class_exists($class)) {
                throw new InvalidArgumentException("Unknown validation rule [$name].");
            }

            $error = (new $class())->check($label, $value, $params);

            if ($error !== false) {
                $this->errors[$key] = $error; // first error per field is enough
                break;
            }
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function parse(string $rule): array
    {
        [$name, $params] = array_pad(explode(':', $rule, 2), 2, '');

        return [ucfirst($name), $params === '' ? [] : explode(',', $params)];
    }
}
