<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\View\Html;
use App\View\Icons;
use Tests\TestCase;

final class HtmlTest extends TestCase
{
    public function testEscapesQuotesAndTags(): void
    {
        $this->assertSame('&lt;script&gt;&quot;x&quot; &amp; &#039;y&#039;', Html::escape('<script>"x" & \'y\''));
    }

    public function testCategoryColorIgnoresCase(): void
    {
        $this->assertSame(Html::categoryClass('Work'), Html::categoryClass('work'));
        $this->assertTrue((bool) preg_match('/^cat-[0-7]$/', Html::categoryClass('Home')));
    }

    public function testIcons(): void
    {
        $this->assertTrue(Icons::has('check'));
        $this->assertTrue(str_contains(Icons::svg('check', 'big'), 'class="icon big"'));
        $this->assertFalse(Icons::has('missing'));
    }
}
