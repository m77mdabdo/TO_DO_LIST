<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/** One-time links sent by email ("password_reset", "email_verification"). */
interface UserTokens
{
    public const PASSWORD_RESET = 'password_reset';
    public const EMAIL_VERIFICATION = 'email_verification';

    public function create(int $userId, string $purpose, string $tokenHash, string $expiresAt, string $now): void;

    /** The user id of an unused, unexpired token, or null. */
    public function findUsable(string $purpose, string $tokenHash, string $now): ?int;

    /**
     * Marks the token used. Returns false when another request used it
     * first, so a link can never be spent twice even in a race.
     */
    public function consume(string $purpose, string $tokenHash, string $now): bool;

    /** Retires every open token of that kind for the user (a newer link, or the job is done). */
    public function revokeAll(int $userId, string $purpose, string $now): void;

    /** How many tokens of that kind were made for the user since $since. */
    public function countSince(int $userId, string $purpose, string $since): int;

    /** Deletes tokens that expired or were used before $before. */
    public function prune(string $before): int;
}
