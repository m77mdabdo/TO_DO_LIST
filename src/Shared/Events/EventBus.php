<?php

declare(strict_types=1);

namespace App\Shared\Events;

/**
 * The only channel modules use to tell each other that something happened.
 * The publisher never knows who listens, so modules stay decoupled.
 */
interface EventBus
{
    /**
     * The listener names the concrete event it handles, e.g. fn (CardMoved $e) => ...
     * It is only ever called with events published under $eventName.
     *
     * @template TEvent of DomainEvent
     * @param callable(TEvent): void $listener
     */
    public function listen(string $eventName, callable $listener): void;

    public function publish(DomainEvent $event): void;

    /**
     * Changes each time a fresh, outermost dispatch begins. Events published
     * while listeners run are queued into the same round, so a listener can
     * tell "caused by what I just did" from "a new action" (automations use
     * it to stop loops).
     */
    public function round(): int;
}
