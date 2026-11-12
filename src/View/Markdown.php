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

    public function toHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", mb_substr($text, 0, self::MAX_LENGTH));
        $lines = explode("\n", $text);
        $html = [];
        $paragraph = [];
        $list = null;   // 'ul' | 'ol' | null
        $items = [];
        $quote = [];

        $flushParagraph = function () use (&$paragraph, &$html): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . implode('<br>', array_map([$this, 'inline'], $paragraph)) . '</p>';
                $paragraph = [];
            }
        };
        $flushList = function () use (&$list, &$items, &$html): void {
            if ($list !== null) {
                $html[] = "<$list>" . implode('', array_map(fn (string $item) => '<li>' . $this->inline($item) . '</li>', $items)) . "</$list>";
                $list = null;
                $items = [];
            }
        };
        $flushQuote = function () use (&$quote, &$html): void {
            if ($quote !== []) {
                $html[] = '<blockquote>' . implode('<br>', array_map([$this, 'inline'], $quote)) . '</blockquote>';
                $quote = [];
            }
        };
        $flushAll = static function () use ($flushParagraph, $flushList, $flushQuote): void {
            $flushParagraph();
            $flushList();
            $flushQuote();
        };

        for ($i = 0, $n = count($lines); $i < $n; $i++) {
            $line = rtrim($lines[$i]);

            if (str_starts_with(ltrim($line), '```')) {
                $flushAll();
                $code = [];
                for ($i++; $i < $n && !str_starts_with(ltrim($lines[$i]), '```'); $i++) {
                    $code[] = $lines[$i];
                }
                $html[] = '<pre><code>' . self::escape(implode("\n", $code)) . '</code></pre>';
                continue;
            }

            if (trim($line) === '') {
                $flushAll();
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $m)) {
                $flushAll();
                $level = strlen($m[1]) + 2; // # -> h3: the card title is the page's main heading
                $html[] = "<h$level>" . $this->inline($m[2]) . "</h$level>";
                continue;
            }

            if (preg_match('/^\s*[-*+]\s+(.+)$/', $line, $m) || preg_match('/^\s*\d{1,9}[.)]\s+(.+)$/', $line, $n2)) {
                $type = isset($m[1]) ? 'ul' : 'ol';
                $flushParagraph();
                $flushQuote();
                if ($list !== $type) {
                    $flushList();
                    $list = $type;
                }
                $items[] = $m[1] ?? $n2[1];
                unset($m, $n2);
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $flushParagraph();
                $flushList();
                $quote[] = $m[1];
                continue;
            }

            $flushList();
            $flushQuote();
            $paragraph[] = $line;
        }

        $flushAll();

        return implode("\n", $html);
    }

    /** Inline markup on one line of raw text. */
    private function inline(string $raw): string
    {
        $this->held = [];
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

        return preg_replace_callback('/\x{E000}(\d+)\x{E001}/u', fn (array $m) => $this->held[(int) $m[1]], $text);
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
