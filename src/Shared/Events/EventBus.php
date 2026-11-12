<?php

declare(strict_types=1);

namespace App\Shared\Events;

/**
 * The only channel modules use to tell each other that something happened.
 * The publisher never knows who listens, so modules stay decoupled.
 */
interface EventBus
{
    /** @param callable(DomainEvent): void $listener */
    public function listen(string $eventName, callable $listener): void;

    public function publish(DomainEvent $event): void;
}
