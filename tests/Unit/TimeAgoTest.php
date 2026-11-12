<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\FrozenClock;
use App\Support\Greeting;
use App\Support\TimeAgo;
use DateTimeImmutable;
use Tests\TestCase;

final class TimeAgoTest extends TestCase
{
    public function testFormatsRecentMoments(): void
    {
        $clock = new FrozenClock('2026-01-20 12:00:00');
        $ago = new TimeAgo($clock);

        $this->assertSame('just now', $ago->format(new DateTimeImmutable('2026-01-20 11:59:30')));
        $this->assertSame('5m ago', $ago->format(new DateTimeImmutable('2026-01-20 11:55:00')));
        $this->assertSame('3h ago', $ago->format(new DateTimeImmutable('2026-01-20 09:00:00')));
        $this->assertSame('2d ago', $ago->format(new DateTimeImmutable('2026-01-18 12:00:00')));
        $this->assertSame('2026-01-02', $ago->format(new DateTimeImmutable('2026-01-02 12:00:00')));
        $this->assertSame('', $ago->format(null));
    }

    public function testGreetingFollowsTheHour(): void
    {
        $this->assertSame('Good morning', (new Greeting(new FrozenClock('2026-01-20 08:00')))->forNow());
        $this->assertSame('Working late', (new Greeting(new FrozenClock('2026-01-20 02:00')))->forNow());
    }
}
