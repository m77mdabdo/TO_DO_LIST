<?php

declare(strict_types=1);

namespace App\Modules\Access\Contract;

/**
 * A user's role on one board. Whether they are a guest is a workspace
 * matter; on the board, a guest simply holds one of these roles.
 */
enum BoardRole: string
{
    case Manager = 'manager';
    case Member = 'member';
    case Viewer = 'viewer';

    public function permissions(): int
    {
        return match ($this) {
            self::Manager => Permission::mask(Permission::cases()),
            self::Member  => Permission::mask([
                Permission::ViewBoard, Permission::EditCards, Permission::MoveCards, Permission::ArchiveItems,
                Permission::Comment, Permission::Vote, Permission::ManageLabels, Permission::Export, Permission::ViewActivity,
            ]),
            // Viewers never edit or move work, but may join the conversation.
            self::Viewer  => Permission::mask([Permission::ViewBoard, Permission::Comment, Permission::Vote, Permission::ViewActivity]),
        };
    }

    public function allows(Permission $permission): bool
    {
        return ($this->permissions() & $permission->value) !== 0;
    }

    /** Higher means more rights: viewer < member < manager. */
    public function rank(): int
    {
        return match ($this) {
            self::Viewer  => 0,
            self::Member  => 1,
            self::Manager => 2,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
