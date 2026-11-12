<?php

declare(strict_types=1);

namespace App\Modules\Access\Contract;

/**
 * Fine-grained board permissions as bit flags. A role is a bitmask of
 * these, so "may this role do X?" is a single AND: O(1), no lookups.
 */
enum Permission: int
{
    case ViewBoard      = 1 << 0;
    case EditCards      = 1 << 1;  // create and edit cards and lists
    case MoveCards      = 1 << 2;  // move cards and lists, reorder
    case ArchiveItems   = 1 << 3;
    case DeleteItems    = 1 << 4;  // permanent delete
    case ManageMembers  = 1 << 5;
    case Comment        = 1 << 6;
    case Vote           = 1 << 7;
    case ManageLabels   = 1 << 8;
    case ManageInvites  = 1 << 9;
    case ManageSettings = 1 << 10; // name, privacy, archive the board
    case Export         = 1 << 11;
    case ViewActivity   = 1 << 12;

    /** @param list<self> $permissions */
    public static function mask(array $permissions): int
    {
        return array_reduce($permissions, static fn (int $mask, self $p) => $mask | $p->value, 0);
    }
}
