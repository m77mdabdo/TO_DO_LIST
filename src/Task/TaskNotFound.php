<?php

declare(strict_types=1);

namespace App\Task;

use RuntimeException;

final class TaskNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("We couldn't find that task.");
    }
}
