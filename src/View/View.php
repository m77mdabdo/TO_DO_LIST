<?php

declare(strict_types=1);

namespace App\View;

use App\Http\Url;
use App\I18n\Translator;
use App\Security\Csrf;
use App\Session\Session;
use RuntimeException;

/**
 * Renders plain PHP templates from views/. Inside a template, $this is the
 * view, so helpers read as $this->e(), $this->url(), $this->icon() …
 */
final class View
{
    /** @var array<string, mixed> shared with every template */
    private array $shared = [];

    public function __construct(
        private string $directory,
        private Url $url,
        private Csrf $csrf,
        private Session $session,
        public readonly FormState $form,
        private ?Translator $translator = null,
    ) {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = $this->renderFile($template, $data);

        return $layout === null ? $content : $this->renderFile($layout, $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderFile($template, $data);
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Translated text, NOT escaped: wrap it in e() when printing.
     *
     * @param array<string, string|int> $replace
     */
    public function t(string $text, array $replace = []): string
    {
        return $this->translator?->t($text, $replace) ?? strtr($text, array_combine(
            array_map(static fn ($k) => ':' . $k, array_keys($replace)),
            array_map('strval', $replace),
        ) ?: []);
    }

    /**
     * Translated and HTML-escaped, the common case in templates.
     *
     * @param array<string, string|int> $replace
     */
    public function te(string $text, array $replace = []): string
    {
        return $this->e($this->t($text, $replace));
    }

    public function locale(): string
    {
        return $this->translator?->locale() ?? 'en';
    }

    public function direction(): string
    {
        return $this->translator?->direction() ?? 'ltr';
    }

    public function url(string $path = '/'): string
    {
        return $this->url->to($path);
    }

    public function asset(string $path): string
    {
        return $this->url->asset($path);
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
        return '<input type="hidden" name="_token" value="' . $this->csrfToken() . '">';
    }

    public function flash(string $key): ?string
    {
        $value = $this->session->pullFlash($key);

        return is_string($value) ? $value : null;
    }

    /** Stable color class for a list name, so "Work" always has the same color. */
    public function categoryClass(?string $category): string
    {
        return 'cat-' . (crc32(mb_strtolower((string) $category)) % 8);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $file = $this->directory . '/' . $template . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View [$template] not found.");
        }

        extract($this->shared + $data, EXTR_SKIP);
        ob_start();

        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
