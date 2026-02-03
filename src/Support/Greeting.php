<?php

declare(strict_types=1);

namespace App\Support;

final class Greeting
{
    public function __construct(private Clock $clock)
    {
    }

    public function forNow(): string
    {
        $hour = (int) $this->clock->now()->format('G');

        return match (true) {
            $hour < 5  => 'Working late',
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };
    }
}
