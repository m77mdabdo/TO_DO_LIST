<?php

declare(strict_types=1);

namespace App\Shared\Events;

/**
 * Wraps a real bus and remembers every published event.
 * Handy in tests to assert "this action announced that fact".
 */
final class RecordingEventBus implements EventBus
{
    /** @var list<DomainEvent> */
    private array $published = [];

    public function __construct(private EventBus $inner = new SyncEventBus())
    {
    }

    public function listen(string $eventName, callable $listener): void
    {
        $this->inner->listen($eventName, $listener);
    }

    public function publish(DomainEvent $event): void
    {
        $this->published[] = $event;
        $this->inner->publish($event);
    }

    public function round(): int
    {
        return $this->inner->round();
    }

    /** @return list<DomainEvent> */
    public function published(?string $eventName = null): array
    {
        if ($eventName === null) {
            return $this->published;
        }

        return array_values(array_filter($this->published, static fn (DomainEvent $e) => $e::name() === $eventName));
    }
}
