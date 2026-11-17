<?php

declare(strict_types=1);

namespace App\Shared\Export;

use Closure;

/**
 * What each module holds about one person, for "Download my data": the
 * profile, workspaces, comments they wrote, notifications and settings.
 * Each part only returns the person's own data.
 *
 * Modules also say how to erase what is only about that person (settings,
 * memberships, notifications) when they delete their account. Shared work
 * such as cards and comments is not erased.
 */
final class PersonalData
{
    /** @var array<string, Closure(int): mixed> */
    private array $parts = [];
    /** @var array<string, Closure(int): void> */
    private array $erasers = [];

    /** @param Closure(int): void $eraser */
    public function eraseWith(string $name, Closure $eraser): void
    {
        $this->erasers[$name] = $eraser;
    }

    public function erase(int $userId): void
    {
        foreach ($this->erasers as $eraser) {
            $eraser($userId);
        }
    }

    /**
     * Deletes the person's rows from tables that are only about them.
     *
     * @param list<string> $tables each with a user_id column
     */
    public static function deleteRows(\PDO $db, int $userId, array $tables): void
    {
        foreach ($tables as $table) {
            $db->prepare('DELETE FROM ' . $table . ' WHERE user_id = ?')->execute([$userId]);
        }
    }

    /** @param Closure(int): mixed $forUser user id => data */
    public function add(string $name, Closure $forUser): void
    {
        $this->parts[$name] = $forUser;
    }

    /** @return array<string, mixed> part name => data */
    public function forUser(int $userId): array
    {
        $out = [];
        foreach ($this->parts as $name => $part) {
            $out[$name] = $part($userId);
        }

        return $out;
    }
}
