<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Discussion\Domain\Mentions;
use Tests\TestCase;

final class MentionsTest extends TestCase
{
    private const PEOPLE = [1 => 'Mona Hassan', 2 => 'Mohamed Abdo', 3 => 'Mohamed Ali', 4 => 'سارة علي'];

    public function testFullNamesAndUniqueFirstNamesMatchIgnoringCase(): void
    {
        $this->assertSame([1, 2], Mentions::find('@mona and @mohamed abdo', self::PEOPLE));
    }

    public function testASharedFirstNameIsNotEnough(): void
    {
        $this->assertSame([], Mentions::find('@Mohamed please', self::PEOPLE));
    }

    public function testTheNameMustEndAtAWordBoundary(): void
    {
        $this->assertSame([], Mentions::find('@Monalisa', self::PEOPLE));
        $this->assertSame([1], Mentions::find('(@Mona)', self::PEOPLE));
    }

    public function testArabicNames(): void
    {
        $this->assertSame([4], Mentions::find('شكراً @سارة', self::PEOPLE));
    }

    public function testNoAtSignMeansNoWork(): void
    {
        $this->assertSame([], Mentions::find('Mona Hassan', self::PEOPLE));
    }
}
