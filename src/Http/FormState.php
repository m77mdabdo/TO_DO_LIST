<?php

declare(strict_types=1);

namespace App\Http;

use App\Session\Session;

/**
 * Carries validation errors and the submitted values through a redirect,
 * so a form can be shown again with what the user typed.
 */
final class FormState
{
    private const ERRORS = 'errors';
    private const OLD    = 'old';

    /** Never sent back to the browser. */
    private const HIDDEN = ['_token', 'password', 'password_confirmation'];

    /** @var array<string, string>|null */
    private ?array $errors = null;

    /** @var array<string, mixed>|null */
    private ?array $old = null;

    public function __construct(private Session $session)
    {
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $input
     */
    public function remember(array $errors, array $input): void
    {
        $this->session->flash(self::ERRORS, $errors);
        $this->session->flash(self::OLD, array_diff_key($input, array_flip(self::HIDDEN)));
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors ??= (array) $this->session->pull(self::ERRORS, []);
    }

    public function error(string $field): ?string
    {
        return $this->errors()[$field] ?? null;
    }

    public function old(string $field, string $default = ''): string
    {
        $this->old ??= (array) $this->session->pull(self::OLD, []);
        $value = $this->old[$field] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function hasOld(): bool
    {
        $this->old('');

        return $this->old !== [];
    }
}
