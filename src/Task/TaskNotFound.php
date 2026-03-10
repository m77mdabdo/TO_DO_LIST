<?php

declare(strict_types=1);

namespace App\Task;

use App\Support\NotFound;
use RuntimeException;

final class TaskNotFound extends RuntimeException implements NotFound
{
    public function __construct()
    {
        parent::__construct("We couldn't find that task.");
    }
}
