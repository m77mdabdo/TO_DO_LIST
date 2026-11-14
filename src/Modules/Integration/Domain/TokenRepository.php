<?php

declare(strict_types=1);

namespace App\Modules\Integration\Domain;

use App\Modules\Integration\Contract\ApiToken;

interface TokenRepository
{
    /** @param list<int> $boardIds */
    public function create(int $userId, string $name, string $hash, string $scope, array $boardIds, ?string $expiresAt): ApiToken;

    public function findByHash(string $hash): ?ApiToken;

    public function findByPublicId(string $publicId): ?ApiToken;

    /** @return list<ApiToken> the person's tokens, newest first */
    public function forUser(int $userId): array;

    public function touch(int $id): void;

    public function revoke(int $id): void;
}
