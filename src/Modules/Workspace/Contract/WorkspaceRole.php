<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contract;

/** A user's role in a whole workspace (company). Board roles are separate. */
enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Guest = 'guest';

    /** Owners and admins manage every board in the workspace. */
    public function managesAllBoards(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    /** Guests only see the boards they were added to and can't start new ones. */
    public function canCreateBoards(): bool
    {
        return $this !== self::Guest;
    }

    public function isFullMember(): bool
    {
        return $this !== self::Guest;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
