<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Shared\Events\DomainEvent;
use App\Shared\Events\RecordingEventBus;
use App\Shared\Events\SyncEventBus;
use DateTimeImmutable;
use Tests\TestCase;

final class PingHappened implements DomainEvent
{
    public function __construct(public readonly int $n)
    {
    }

    public static function name(): string
    {
        return 'test.ping';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-12 10:00:00');
    }
}

final class EventBusTest extends TestCase
{
    public function testListenersReceiveTheEventInOrder(): void
    {
        $bus = new SyncEventBus();
        $calls = [];
        $bus->listen('test.ping', function (PingHappened $e) use (&$calls): void { $calls[] = "a{$e->n}"; });
        $bus->listen('test.ping', function (PingHappened $e) use (&$calls): void { $calls[] = "b{$e->n}"; });

        $bus->publish(new PingHappened(1));

        $this->assertSame(['a1', 'b1'], $calls);
    }

    public function testEventsWithoutListenersAreFine(): void
    {
        $bus = new SyncEventBus();
        $bus->publish(new PingHappened(1));

        $this->assertFalse($bus->hasListeners('test.ping'));
    }

    public function testEventsPublishedByListenersRunAfterTheCurrentOne(): void
    {
        $bus = new SyncEventBus();
        $calls = [];
        $bus->listen('test.ping', function (PingHappened $e) use (&$calls, $bus): void {
            $calls[] = "start{$e->n}";
            if ($e->n < 3) {
                $bus->publish(new PingHappened($e->n + 1));
            }
            $calls[] = "end{$e->n}";
        });

        $bus->publish(new PingHappened(1));

        $this->assertSame(['start1', 'end1', 'start2', 'end2', 'start3', 'end3'], $calls);
    }

    public function testRecordingBusKeepsWhatWasPublished(): void
    {
        $bus = new RecordingEventBus();
        $bus->publish(new PingHappened(7));

        $this->assertCount(1, $bus->published('test.ping'));
        $this->assertCount(0, $bus->published('test.other'));
    }
}
