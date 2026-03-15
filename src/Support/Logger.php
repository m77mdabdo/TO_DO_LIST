<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

interface Logger
{
    public function error(Throwable $e): void;
}
