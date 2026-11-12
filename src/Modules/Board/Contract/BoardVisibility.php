<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;

enum BoardVisibility: string
{
    /** Only people added to the board (plus workspace owners/admins). The default. */
    case Private = 'private';

    /** Every full member of the workspace can view it. */
    case Workspace = 'workspace';
}
