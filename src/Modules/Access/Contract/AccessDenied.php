<?php

declare(strict_types=1);

namespace App\Modules\Access\Contract;

use App\Http\HttpException;

/**
 * Thrown when a user may not do something on a board.
 * Someone who can't even see the board gets a 404, so ids of private
 * boards can't be probed; someone who can see it but not act gets a 403.
 */
final class AccessDenied extends HttpException
{
    public static function hidden(): self
    {
        return new self(404, "This page doesn't exist.");
    }

    public static function forbidden(): self
    {
        return new self(403, 'You do not have permission to do that.');
    }
}
