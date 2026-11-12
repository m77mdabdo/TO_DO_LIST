<?php

declare(strict_types=1);

namespace App\Validation;

use App\I18n\Translator;
use App\Validation\Rules;
use InvalidArgumentException;

/*
 | Usage:
 |   $result = $validator->validate($input, [
 |       'title' => ['Title', 'required|text|min:2|max:150'],
 |   ]);
 |
 | New rules are added with extend(), so the validator itself never changes.
 | Empty optional fields skip every rule except "required".
 */
final class Validator
{
    /** @var array<string, Rule> */
    private array $rules = [];

    public function __construct(private ?Translator $translator = null)
    {
        $this->extend('required', new Rules\Required())
            ->extend('text', new Rules\Text())
            ->extend('min', new Rules\Min())
            ->extend('max', new Rules\Max())
            ->extend('email', new Rules\Email())
            ->extend('in', new Rules\In())
            ->extend('date', new Rules\Date());
    }

    public function extend(string $name, Rule $rule): self
    {
        $this->rules[strtolower($name)] = $rule;

        return $this;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, array{0: string, 1: string}> $fields field => [label, "rule|rule:param"]
     */
    public function validate(array $input, array $fields): ValidationResult
    {
        $errors = [];

        foreach ($fields as $field => [$label, $ruleList]) {
            $error = $this->firstError($label, $input[$field] ?? null, $ruleList);

            if ($error !== null) {
                $errors[$field] = $error;
            }
        }

        return new ValidationResult($errors);
    }

    private function firstError(string $label, mixed $value, string $ruleList): ?string
    {
        $isEmpty = !is_string($value) || trim($value) === '';

        foreach (explode('|', $ruleList) as $definition) {
            [$name, $params] = $this->parse($definition);

            if ($isEmpty && $name !== 'required') {
                continue;
            }

            $error = $this->rule($name)->check($label, $value, $params);

            if ($error !== null) {
                return $this->message($error, $label, $params);
            }
        }

        return null;
    }

    /** @return array{0: string, 1: list<string>} */
    private function parse(string $definition): array
    {
        [$name, $params] = array_pad(explode(':', $definition, 2), 2, '');

        return [strtolower(trim($name)), $params === '' ? [] : explode(',', $params)];
    }

    private function rule(string $name): Rule
    {
        return $this->rules[$name] ?? throw new InvalidArgumentException("Unknown validation rule [$name].");
    }

    /** @param list<string> $params */
    private function message(string $template, string $label, array $params): string
    {
        $replace = ['label' => $this->translator?->t($label) ?? $label, 'param' => $params[0] ?? ''];

        return $this->translator?->t($template, $replace) ?? strtr($template, [':label' => $replace['label'], ':param' => $replace['param']]);
    }
}
