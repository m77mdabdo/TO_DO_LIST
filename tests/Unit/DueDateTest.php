<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Task\DueDate;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\TestCase;

final class DueDateTest extends TestCase
{
    private DateTimeImmutable $today;

    public function setUp(): void
    {
        $this->today = new DateTimeImmutable('2026-01-14 18:30'); // a Wednesday
    }

    public function testLabelsAreRelativeToToday(): void
    {
        $this->assertSame('Today', DueDate::fromString('2026-01-14')->label($this->today));
        $this->assertSame('Tomorrow', DueDate::fromString('2026-01-15')->label($this->today));
        $this->assertSame('Yesterday', DueDate::fromString('2026-01-13')->label($this->today));
        $this->assertSame('3 days late', DueDate::fromString('2026-01-11')->label($this->today));
        $this->assertSame('Sunday', DueDate::fromString('2026-01-18')->label($this->today));
        $this->assertSame('Feb 2', DueDate::fromString('2026-02-02')->label($this->today));
    }

    public function testStates(): void
    {
        $this->assertSame('overdue', DueDate::fromString('2026-01-10')->state($this->today));
        $this->assertSame('today', DueDate::fromString('2026-01-14')->state($this->today));
        $this->assertSame('soon', DueDate::fromString('2026-01-17')->state($this->today));
        $this->assertSame('later', DueDate::fromString('2026-01-30')->state($this->today));
    }

    public function testRejectsImpossibleDates(): void
    {
        $this->assertThrows(InvalidArgumentException::class, fn () => DueDate::fromString('2026-02-30'));
    }
}
