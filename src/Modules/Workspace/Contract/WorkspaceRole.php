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

    /** Higher means more rights: guest < member < admin < owner. */
    public function rank(): int
    {
        return match ($this) {
            self::Guest  => 0,
            self::Member => 1,
            self::Admin  => 2,
            self::Owner  => 3,
        };
    }

    /**
     * The roles someone with this role may give to others: owners give any
     * role, admins give member or guest, everyone else gives none.
     *
     * @return list<self>
     */
    public function grantable(): array
    {
        return match ($this) {
            self::Owner => [self::Owner, self::Admin, self::Member, self::Guest],
            self::Admin => [self::Member, self::Guest],
            default     => [],
        };
    }

    /** May someone with this role change or remove a member who holds $other? */
    public function outranksForManagement(self $other): bool
    {
        return $this === self::Owner || ($this === self::Admin && $other->rank() < self::Admin->rank());
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
