<?php

declare(strict_types=1);

namespace App\View;

use App\Http\FormState;
use App\Http\UrlGenerator;
use App\Security\Csrf;
use App\Session\Session;
use App\Support\Clock;
use App\Support\Greeting;
use App\Support\TimeAgo;
use DateTimeImmutable;

/**
 * The small toolbox every template gets as $v. It only delegates,
 * so each job still lives in the class that owns it.
 */
final class ViewHelpers
{
    public function __construct(
        private UrlGenerator $urls,
        private Csrf $csrf,
        private FormState $form,
        private Session $session,
        private Clock $clock,
    ) {
    }

    public function e(mixed $value): string
    {
        return Html::escape($value);
    }

    public function url(string $path = '/'): string
    {
        return Html::escape($this->urls->to($path));
    }

    public function asset(string $path): string
    {
        return Html::escape($this->urls->asset($path));
    }

    public function icon(string $name, string $class = ''): string
    {
        return Icons::svg($name, $class);
    }

    public function csrfToken(): string
    {
        return $this->csrf->token();
    }

    public function csrfField(): string
    {
        return '<input type="hidden" name="' . Csrf::FIELD . '" value="' . $this->csrf->token() . '">';
    }

    public function old(string $field, string $default = ''): string
    {
        return $this->form->old($field, $default);
    }

    public function hasOld(): bool
    {
        return $this->form->hasOld();
    }

    public function error(string $field): ?string
    {
        return $this->form->error($field);
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->form->errors();
    }

    /** "true"/"false" for aria-invalid. */
    public function invalid(string $field): string
    {
        return $this->error($field) === null ? 'false' : 'true';
    }

    public function flash(string $key): ?string
    {
        $value = $this->session->pull($key);

        return is_string($value) ? $value : null;
    }

    public function today(): DateTimeImmutable
    {
        return $this->clock->now();
    }

    public function timeAgo(?DateTimeImmutable $moment): string
    {
        return (new TimeAgo($this->clock))->format($moment);
    }

    public function greeting(): string
    {
        return (new Greeting($this->clock))->forNow();
    }

    public function categoryClass(?string $category): string
    {
        return Html::categoryClass($category);
    }
}
