<?php

declare(strict_types=1);

namespace App\Task;

enum Priority: string
{
    case High   = 'high';
    case Normal = 'normal';
    case Low    = 'low';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Lower sorts first on the board. */
    public function weight(): int
    {
        return match ($this) {
            self::High   => 0,
            self::Normal => 1,
            self::Low    => 2,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
