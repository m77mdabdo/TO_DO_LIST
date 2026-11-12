<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contract;

/**
 * Who is making this request. The rest of the app asks this,
 * never the session or the users table directly.
 */
interface Authenticator
{
    public function user(): ?User;

    public function check(): bool;
}
