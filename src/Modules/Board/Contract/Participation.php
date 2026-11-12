<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;

/**
 * Who may comment on, or vote for, the cards of a board. A board setting
 * on top of the role permissions: "members" leaves viewers out.
 */
enum Participation: string
{
    case Off = 'off';
    case Members = 'members';
    case Everyone = 'everyone';

    /** @param bool $canEditCards whether the person may edit cards (managers and members) */
    public function admits(bool $canEditCards): bool
    {
        return match ($this) {
            self::Off      => false,
            self::Members  => $canEditCards,
            self::Everyone => true,
        };
    }
}
