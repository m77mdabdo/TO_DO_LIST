<?php

declare(strict_types=1);

namespace App\I18n;

use App\Session\Session;

/**
 * Gettext-style translations: the English text is the key, so templates
 * stay readable and a missing translation simply shows English.
 *
 *   $t->t('Hello :name', ['name' => 'Sara'])
 *
 * Each locale's file (lang/<code>.php) is loaded once, on first use,
 * into a hash map: every lookup after that is O(1).
 */
final class Translator
{
    public const SUPPORTED = ['ar' => 'rtl', 'en' => 'ltr'];
    private const SESSION_KEY = 'locale';

    /** @var array<string, array<string, string>> */
    private array $catalogs = [];

    public function __construct(
        private string $directory,
        private string $defaultLocale = 'ar',
        private ?Session $session = null,
    ) {
        if (!isset(self::SUPPORTED[$this->defaultLocale])) {
            $this->defaultLocale = 'ar';
        }
    }

    public function locale(): string
    {
        $chosen = $this->session?->get(self::SESSION_KEY);

        return is_string($chosen) && isset(self::SUPPORTED[$chosen]) ? $chosen : $this->defaultLocale;
    }

    public function direction(): string
    {
        return self::SUPPORTED[$this->locale()];
    }

    public function setLocale(string $locale): bool
    {
        if (!isset(self::SUPPORTED[$locale]) || $this->session === null) {
            return false;
        }

        $this->session->put(self::SESSION_KEY, $locale);

        return true;
    }

    /** @param array<string, string|int> $replace */
    public function t(string $text, array $replace = []): string
    {
        $translated = $this->catalog($this->locale())[$text] ?? $text;

        if ($replace === []) {
            return $translated;
        }

        // Longest keys first, so ":name" never eats part of ":names".
        uksort($replace, static fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($replace as $key => $value) {
            $translated = str_replace(':' . $key, (string) $value, $translated);
        }

        return $translated;
    }

    /** @return array<string, string> */
    private function catalog(string $locale): array
    {
        if (!isset($this->catalogs[$locale])) {
            $file = $this->directory . '/' . $locale . '.php';
            $this->catalogs[$locale] = is_file($file) ? (array) require $file : [];
        }

        return $this->catalogs[$locale];
    }
}
