<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Marks a domain exception that means "this thing doesn't exist",
 * so the web layer can answer 404 without the domain knowing about HTTP.
 */
interface NotFound
{
}
