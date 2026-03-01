<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Task\Priority;
use App\Task\Status;
use Tests\TestCase;

final class TaskEnumsTest extends TestCase
{
    public function testStatusesMoveOneColumnAtATime(): void
    {
        $this->assertSame(Status::Doing, Status::Todo->next());
        $this->assertSame(Status::Done, Status::Doing->next());
        $this->assertNull(Status::Done->next());
        $this->assertNull(Status::Todo->previous());
    }

    public function testHighPrioritySortsFirst(): void
    {
        $this->assertTrue(Priority::High->weight() < Priority::Normal->weight());
        $this->assertTrue(Priority::Normal->weight() < Priority::Low->weight());
        $this->assertSame(['high', 'normal', 'low'], Priority::values());
    }
}
