<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

final class TimeAgo
{
    public function __construct(private Clock $clock)
    {
    }

    public function format(?DateTimeImmutable $moment): string
    {
        if ($moment === null) {
            return '';
        }

        $seconds = $this->clock->now()->getTimestamp() - $moment->getTimestamp();

        return match (true) {
            $seconds < 60     => 'just now',
            $seconds < 3600   => intdiv($seconds, 60) . 'm ago',
            $seconds < 86400  => intdiv($seconds, 3600) . 'h ago',
            $seconds < 604800 => intdiv($seconds, 86400) . 'd ago',
            default           => $moment->format('M j'),
        };
    }
}
