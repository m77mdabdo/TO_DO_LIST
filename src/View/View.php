<?php

declare(strict_types=1);

namespace App\View;

use App\Http\Url;
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
