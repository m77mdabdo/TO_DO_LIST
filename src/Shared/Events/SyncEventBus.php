<?php

declare(strict_types=1);

namespace App\Shared\Events;

/**
 * Dispatches events in-process, right away, in registration order.
 *
 * Listeners are kept in a hash map keyed by event name, so publishing is
 * O(1) to find the listeners plus O(k) to call the k that subscribed,
 * never a scan over every listener in the app.
 *
 * Events published by a listener are queued and dispatched after the
 * current one finishes (breadth-first), which keeps ordering predictable
 * and stops a listener chain from recursing without limit.
 */
final class SyncEventBus implements EventBus
{
    /** @var array<string, list<callable>> each listener takes the event published under its key */
    private array $listeners = [];

    /** @var list<DomainEvent> */
    private array $queue = [];

    private bool $dispatching = false;

    public function listen(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    public function publish(DomainEvent $event): void
    {
        $this->queue[] = $event;

        if ($this->dispatching) {
            return;
        }

        $this->dispatching = true;

        try {
            // Index walk instead of array_shift(): shifting re-indexes the array, O(n) each time.
            for ($i = 0; $i < count($this->queue); $i++) {
                $current = $this->queue[$i];

                foreach ($this->listeners[$current::name()] ?? [] as $listener) {
                    $listener($current);
                }
            }
        } finally {
            $this->queue = [];
            $this->dispatching = false;
        }
    }

    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]);
    }
}
