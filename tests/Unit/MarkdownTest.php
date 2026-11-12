<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\View\Markdown;
use Tests\TestCase;

final class MarkdownTest extends TestCase
{
    public function testRawHtmlIsAlwaysShownAsText(): void
    {
        $html = $this->md('<script>alert(1)</script> <img src=x onerror=alert(1)>');

        $this->assertFalse(str_contains($html, '<script'));
        $this->assertFalse(str_contains($html, '<img'));
        $this->assertTrue(str_contains($html, '&lt;script&gt;'));
    }

    public function testLinksOnlyAllowSafeSchemes(): void
    {
        $this->assertFalse(str_contains($this->md('[click](javascript:alert(1))'), 'href'));
        $this->assertFalse(str_contains($this->md('[x](data:text/html;base64,AAAA)'), 'href'));

        $html = $this->md('[Docs](https://example.com/a?b=1&c=2)');
        $this->assertTrue(str_contains($html, '<a href="https://example.com/a?b=1&amp;c=2" target="_blank" rel="noopener noreferrer nofollow">Docs</a>'));
    }

    public function testQuotesCannotBreakOutOfAnAttribute(): void
    {
        $html = $this->md('[x](https://example.com/"onmouseover="alert(1))');

        $this->assertFalse(str_contains($html, '"onmouseover'));
    }

    public function testBareLinksStopBeforePunctuation(): void
    {
        $this->assertSame(
            '<p>See <a href="https://example.com/x" target="_blank" rel="noopener noreferrer nofollow">https://example.com/x</a>.</p>',
            $this->md('See https://example.com/x.'),
        );
    }

    public function testInlineFormatting(): void
    {
        $this->assertSame(
            '<p><strong>bold</strong>, <em>italic</em>, <del>gone</del> and <code>**not bold**</code></p>',
            $this->md('**bold**, *italic*, ~~gone~~ and `**not bold**`'),
        );
        $this->assertSame('<p>snake_case_name stays</p>', $this->md('snake_case_name stays'));
    }

    public function testBlocks(): void
    {
        $html = $this->md("# Plan\n\n- one\n- two\n\n1. first\n2. second\n\n> quoted\n\n```\n<b>code</b>\n```\nline one\nline two");

        $this->assertSame(
            "<h3>Plan</h3>\n<ul><li>one</li><li>two</li></ul>\n<ol><li>first</li><li>second</li></ol>\n"
            . "<blockquote>quoted</blockquote>\n<pre><code>&lt;b&gt;code&lt;/b&gt;</code></pre>\n<p>line one<br>line two</p>",
            $html,
        );
    }

    public function testArabicTextPassesThrough(): void
    {
        $this->assertSame('<p><strong>مهم</strong> جداً</p>', $this->md('**مهم** جداً'));
    }

    public function testUserTextCannotForgeAHeldFragment(): void
    {
        $this->assertFalse(str_contains($this->md("\u{E000}0\u{E001} [a](https://x.io)"), "\u{E000}"));
    }

    private function md(string $text): string
    {
        return (new Markdown())->toHtml($text);
    }
}
