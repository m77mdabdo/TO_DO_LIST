<?php

declare(strict_types=1);

namespace App\View;

/**
 * A small, safe Markdown subset for card descriptions and comments.
 *
 * Everything is HTML-escaped first, so raw HTML in the input is always shown
 * as text, never run. Markup is then added for:
 *
 *   # / ## / ### headings      - or * bullet lists      1. numbered lists
 *   > quotes                    ``` fenced code ```      `inline code`
 *   **bold**  *italic*  _italic_  ~~strike~~  [text](https://link)  bare https://links
 *
 * Links keep only http, https and mailto (no javascript: or data:) and open
 * in a new tab without passing the opener or the referrer. Each line is
 * visited once and every pattern is anchored or lazy, so the cost is linear
 * in the length of the text.
 */
final class Markdown
{
    private const MAX_LENGTH = 20000;

    /** @var list<string> finished HTML fragments kept out of later passes */
    private array $held = [];

    /** @var list<string> blocks rendered so far */
    private array $blocks = [];

    /** @var list<string> raw lines of the paragraph being read */
    private array $paragraph = [];

    /** @var list<string> raw lines of the quote being read */
    private array $quote = [];

    /** @var list<string> raw text of the list items being read */
    private array $items = [];

    /** 'ul', 'ol', or '' when no list is open */
    private string $list = '';

    public function toHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", mb_substr($text, 0, self::MAX_LENGTH));
        $lines = explode("\n", $text);
        $this->blocks = $this->paragraph = $this->quote = $this->items = [];
        $this->list = '';

        for ($i = 0, $n = count($lines); $i < $n; $i++) {
            $line = rtrim($lines[$i]);

            if (str_starts_with(ltrim($line), '```')) {
                $this->flushAll();
                $code = [];
                for ($i++; $i < $n && !str_starts_with(ltrim($lines[$i]), '```'); $i++) {
                    $code[] = $lines[$i];
                }
                $this->blocks[] = '<pre><code>' . self::escape(implode("\n", $code)) . '</code></pre>';
                continue;
            }

            if (trim($line) === '') {
                $this->flushAll();
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $m)) {
                $this->flushAll();
                $level = strlen($m[1]) + 2; // # -> h3: the card title is the page's main heading
                $this->blocks[] = "<h$level>" . $this->inline($m[2]) . "</h$level>";
                continue;
            }

            $type = '';
            if (preg_match('/^\s*[-*+]\s+(.+)$/', $line, $m)) {
                $type = 'ul';
            } elseif (preg_match('/^\s*\d{1,9}[.)]\s+(.+)$/', $line, $m)) {
                $type = 'ol';
            }
            if ($type !== '') {
                $this->flushParagraph();
                $this->flushQuote();
                if ($this->list !== $type) {
                    $this->flushList();
                    $this->list = $type;
                }
                $this->items[] = $m[1];
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $this->flushParagraph();
                $this->flushList();
                $this->quote[] = $m[1];
                continue;
            }

            $this->flushList();
            $this->flushQuote();
            $this->paragraph[] = $line;
        }

        $this->flushAll();
        $html = implode("\n", $this->blocks);
        $this->blocks = [];

        return $html;
    }

    private function flushAll(): void
    {
        $this->flushParagraph();
        $this->flushList();
        $this->flushQuote();
    }

    private function flushParagraph(): void
    {
        if ($this->paragraph !== []) {
            $this->blocks[] = '<p>' . implode('<br>', array_map($this->inline(...), $this->paragraph)) . '</p>';
            $this->paragraph = [];
        }
    }

    private function flushList(): void
    {
        if ($this->list !== '') {
            $items = array_map(fn (string $item): string => '<li>' . $this->inline($item) . '</li>', $this->items);
            $this->blocks[] = "<{$this->list}>" . implode('', $items) . "</{$this->list}>";
            $this->list = '';
            $this->items = [];
        }
    }

    private function flushQuote(): void
    {
        if ($this->quote !== []) {
            $this->blocks[] = '<blockquote>' . implode('<br>', array_map($this->inline(...), $this->quote)) . '</blockquote>';
            $this->quote = [];
        }
    }

    /** Inline markup on one line of raw text. */
    private function inline(string $raw): string
    {
        $text = self::escape($raw);

        // Code spans first: nothing inside them is formatted.
        $text = preg_replace_callback('/`([^`]+)`/', fn (array $m) => $this->hold('<code>' . $m[1] . '</code>'), $text);

        // [text](url)
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function (array $m): string {
            $url = self::safeUrl($m[2]);

            return $url === null ? $m[0] : $this->hold(self::link($url, $m[1]));
        }, $text);

        // Bare links.
        $text = preg_replace_callback('#\bhttps?://(?:(?!&quot;|&\#039;|&gt;)[^\s<\x{E000}])+#iu', function (array $m): string {
            // Sentence punctuation right after a link isn't part of it.
            $url = rtrim($m[0], '.,:;!?)');

            return $this->hold(self::link($url, $url)) . substr($m[0], strlen($url));
        }, $text);

        $text = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/', '<del>$1</del>', $text);
        $text = preg_replace('/(?<![\w*])\*(?=\S)(.+?)(?<=\S)\*(?![\w*])/', '<em>$1</em>', $text);
        $text = preg_replace('/(?<!\w)_(?=\S)(.+?)(?<=\S)_(?!\w)/', '<em>$1</em>', $text);

        $html = preg_replace_callback('/\x{E000}(\d+)\x{E001}/u', fn (array $m): string => $this->held[(int) $m[1]] ?? '', $text);
        $this->held = [];

        return $html;
    }

    /** Parks a finished fragment behind a private-use marker until the end. */
    private function hold(string $html): string
    {
        $this->held[] = $html;

        return "\u{E000}" . (count($this->held) - 1) . "\u{E001}";
    }

    /** The URL (still HTML-escaped) when its scheme is allowed, else null. */
    private static function safeUrl(string $escapedUrl): ?string
    {
        $decoded = html_entity_decode($escapedUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_match('#^(https?://|mailto:)[^\s]+$#i', $decoded) ? $escapedUrl : null;
    }

    private static function link(string $escapedUrl, string $label): string
    {
        return '<a href="' . $escapedUrl . '" target="_blank" rel="noopener noreferrer nofollow">' . $label . '</a>';
    }

    private static function escape(string $text): string
    {
        // Private-use markers can't come from the user, so they can't forge a held fragment.
        $text = str_replace(["\u{E000}", "\u{E001}"], '', $text);

        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
