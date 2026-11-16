<?php

declare(strict_types=1);

namespace App\Modules\Board\Domain;

/**
 * Searches a person named and kept. Only the owner sees or changes them;
 * the results are worked out again, with the viewer's permissions, each
 * time one is opened.
 *
 * @phpstan-type Saved array{id: int, public_id: string, name: string, query: string, updated_at: string}
 */
interface SavedSearches
{
    public const MAX_PER_USER = 50;

    /** @return list<array{id: int, public_id: string, name: string, query: string, updated_at: string}> by name */
    public function forUser(int $userId): array;

    /** @return array{id: int, public_id: string, name: string, query: string, updated_at: string}|null only when $userId owns it */
    public function find(int $userId, string $publicId): ?array;

    public function create(int $userId, string $name, string $query): string;

    public function update(int $id, string $name, string $query): void;

    public function delete(int $id): void;
}
