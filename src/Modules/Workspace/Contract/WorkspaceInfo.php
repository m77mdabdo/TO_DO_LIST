<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contract;

/** A workspace as anyone may see it named, e.g. on an invitation: no role attached. */
final class WorkspaceInfo
{
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly string $companyName,
    ) {
    }
}
