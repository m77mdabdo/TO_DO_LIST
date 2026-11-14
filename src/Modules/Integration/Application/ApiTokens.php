<?php

declare(strict_types=1);

namespace App\Modules\Integration\Application;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Integration\Contract\ApiToken;
use App\Modules\Integration\Domain\TokenRepository;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;

/**
 * Personal API tokens. A token names the boards it may use and whether it
 * may only read or also add cards; on every call it is also limited to what
 * its owner may still do, so taking someone off a board takes the token too.
 */
final class ApiTokens
{
    public const PREFIX = 'tdb_';
    public const MAX_PER_USER = 20;
    public const EXPIRY_DAYS = [30, 90, 365, 0];

    public function __construct(
        private TokenRepository $tokens,
        private AccessGate $gate,
        private BoardDirectory $boards,
        private Clock $clock,
    ) {
    }

    /** @return list<ApiToken> */
    public function forUser(int $userId): array
    {
        return $this->tokens->forUser($userId);
    }

    /**
     * Makes a token and returns it with its secret, which is shown once.
     *
     * @param list<string> $boardPublicIds
     * @return array{0: ApiToken, 1: string}
     */
    public function create(int $userId, string $name, string $scope, array $boardPublicIds, int $days): array
    {
        $name = trim($name);
        $errors = [];
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Give the token a name (up to 100 characters).';
        }
        if (!in_array($scope, ApiToken::SCOPES, true)) {
            $errors['scope'] = 'Choose what the token may do.';
        }
        $boardIds = [];
        foreach (array_unique($boardPublicIds) as $publicId) {
            $board = $this->boards->findByPublicId((string) $publicId);
            if ($board === null || !$this->gate->can($userId, $board->id, Permission::ViewBoard)) {
                $errors['boards'] = 'Choose boards you can see.';
                continue;
            }
            $boardIds[] = $board->id;
        }
        if ($boardIds === [] && !isset($errors['boards'])) {
            $errors['boards'] = 'Choose at least one board. A token never gets every board automatically.';
        }
        if (count($this->tokens->forUser($userId)) >= self::MAX_PER_USER) {
            $errors['name'] = 'You can have at most 20 tokens. Revoke one first.';
        }
        if ($errors !== []) {
            throw new ValidationException(new ValidationResult($errors));
        }

        $secret = self::PREFIX . bin2hex(random_bytes(20));
        $days = in_array($days, self::EXPIRY_DAYS, true) ? $days : 90;
        $expires = $days > 0 ? $this->clock->now()->modify("+$days days")->format('Y-m-d H:i:s') : null;

        return [$this->tokens->create($userId, $name, hash('sha256', $secret), $scope, $boardIds, $expires), $secret];
    }

    public function revoke(int $userId, string $publicId): void
    {
        $token = $this->tokens->findByPublicId($publicId);
        if ($token === null || $token->userId !== $userId) {
            throw AccessDenied::hidden();
        }
        $this->tokens->revoke($token->id);
    }

    /** The active token for a bearer secret, or null. */
    public function authenticate(?string $secret): ?ApiToken
    {
        if ($secret === null || !str_starts_with($secret, self::PREFIX) || strlen($secret) > 100) {
            return null;
        }
        $token = $this->tokens->findByHash(hash('sha256', $secret));
        if ($token === null || !$token->isActive($this->clock->now()->format('Y-m-d H:i:s'))) {
            return null;
        }
        $this->tokens->touch($token->id);

        return $token;
    }

    /** Whether the token may use the board for $permission (its own limits and its owner's). */
    public function allows(ApiToken $token, int $boardId, Permission $permission): bool
    {
        if (!in_array($boardId, $token->boardIds, true) || ($permission !== Permission::ViewBoard && !$token->canWrite())) {
            return false;
        }
        $board = $this->boards->find($boardId);

        return $board !== null && !$board->archived && $this->gate->can($token->userId, $boardId, $permission);
    }
}
