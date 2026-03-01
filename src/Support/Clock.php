<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * Anything that needs "now" asks a Clock, so tests can freeze time.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
