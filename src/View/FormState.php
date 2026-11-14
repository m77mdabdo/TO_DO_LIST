<?php

declare(strict_types=1);

namespace App\View;

use App\Session\Session;

/**
 * Carries a failed form's errors and old input across the redirect back.
 */
final class FormState
{
    private const NEVER_KEPT = ['_token', 'password', 'password_confirmation'];

    /** @var array<string, string>|null */
    private ?array $errors = null;

    /** @var array<string, mixed>|null */
    private ?array $old = null;

    public function __construct(private Session $session)
    {
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    public function keep(array $errors, array $input): void
    {
        $this->session->flash('errors', $errors);
        $this->session->flash('old', array_diff_key($input, array_flip(self::NEVER_KEPT)));
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors ??= (array) $this->session->pullFlash('errors', []);
    }

    public function error(string $field): ?string
    {
        return $this->errors()[$field] ?? null;
    }

    public function old(string $field, string $default = ''): string
    {
        $this->old ??= (array) $this->session->pullFlash('old', []);

        return is_string($this->old[$field] ?? null) ? $this->old[$field] : $default;
    }

    /** @return list<string> the strings sent for a multi-value field like boards[] */
    public function oldArray(string $field): array
    {
        $this->old ??= (array) $this->session->pullFlash('old', []);
        $values = $this->old[$field] ?? [];

        return is_array($values) ? array_values(array_filter($values, 'is_string')) : [];
    }
}
