<?php

declare(strict_types=1);

namespace App\Modules\LegacyImport\Application;

final class ImportReport
{
    /** @param list<string> $problems */
    public function __construct(
        public readonly int $userId,
        public readonly int $importedNow,
        public readonly int $tasksTotal,
        public readonly int $importedTotal,
        public readonly array $problems = [],
    ) {
    }

    /** Every old task of this user now has a card. */
    public function isComplete(): bool
    {
        return $this->tasksTotal === $this->importedTotal && $this->problems === [];
    }
}
