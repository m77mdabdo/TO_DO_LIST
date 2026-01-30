<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $now = 'now')
    {
        $this->now = new DateTimeImmutable($now);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function travel(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
