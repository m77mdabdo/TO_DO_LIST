<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Modules\Identity\Contract\User;

interface UserRepository
{
    public function find(int $id): ?User;

    public function emailExists(string $email): bool;

    /** The stored password hash for an email, or null when there's no such user. */
    public function credentials(string $email): ?Credentials;

    public function create(string $name, string $email, string $passwordHash): User;

    /** Same hash, new format (rehash on login): sessions stay signed in. */
    public function updatePassword(int $id, string $passwordHash): void;

    /** A new password: bumps auth_version, which signs out every other session. */
    public function changePassword(int $id, string $passwordHash): void;

    /** The user, only while the session's auth version is still current. */
    public function findForSession(int $id, int $authVersion): ?User;

    public function authVersion(int $id): int;

    public function findByEmail(string $email): ?User;

    public function markEmailVerified(int $id): void;

    /** Signs out every session that holds the old number. */
    public function bumpAuthVersion(int $id): void;

    public function updateProfile(int $id, string $name, ?string $locale): void;

    public function locale(int $id): ?string;

    /** @return array{secret: string, recovery: string}|null the encrypted secret and the recovery JSON, when two-step sign-in is on */
    public function twoFactor(int $id): ?array;

    public function setTwoFactor(int $id, ?string $encryptedSecret, ?string $recoveryJson): void;

    public function setRecovery(int $id, string $recoveryJson): void;

    /** Removes the personal data and makes the account unusable; the row stays for the authorship of old work. */
    public function anonymize(int $id, string $unusablePasswordHash): void;
}
