<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Contract\User;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Identity\Domain\Credentials;
use App\Modules\Identity\Domain\UserRepository;
use App\Support\Clock;
use PDO;

final class PdoUserRepository implements UserRepository, UserDirectory
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function find(int $id): ?User
    {
        $stmt = $this->db->prepare('SELECT id, name, email, email_verified_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT id, name, email, email_verified_at FROM users WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $users = [];
        foreach ($stmt->fetchAll() as $row) {
            $users[(int) $row['id']] = User::fromRow($row);
        }

        return $users;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([self::normalize($email)]);

        return (bool) $stmt->fetchColumn();
    }

    public function credentials(string $email): ?Credentials
    {
        $stmt = $this->db->prepare('SELECT id, password FROM users WHERE email = ? AND deleted_at IS NULL');
        $stmt->execute([self::normalize($email)]);
        $row = $stmt->fetch();

        return $row ? new Credentials((int) $row['id'], (string) $row['password']) : null;
    }

    public function create(string $name, string $email, string $passwordHash): User
    {
        $this->db->prepare('INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$name, self::normalize($email), $passwordHash, $this->clock->now()->format('Y-m-d H:i:s')]);

        return $this->find((int) $this->db->lastInsertId());
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$passwordHash, $id]);
    }

    public function findForSession(int $id, int $authVersion): ?User
    {
        $stmt = $this->db->prepare('SELECT id, name, email, email_verified_at FROM users WHERE id = ? AND auth_version = ? AND deleted_at IS NULL');
        $stmt->execute([$id, $authVersion]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function authVersion(int $id): int
    {
        $stmt = $this->db->prepare('SELECT auth_version FROM users WHERE id = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn();
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('SELECT id, name, email, email_verified_at FROM users WHERE email = ?');
        $stmt->execute([self::normalize($email)]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function changePassword(int $id, string $passwordHash): void
    {
        $this->db->prepare('UPDATE users SET password = ?, auth_version = auth_version + 1 WHERE id = ?')->execute([$passwordHash, $id]);
    }

    public function markEmailVerified(int $id): void
    {
        $this->db->prepare('UPDATE users SET email_verified_at = ? WHERE id = ? AND email_verified_at IS NULL')
            ->execute([$this->clock->now()->format('Y-m-d H:i:s'), $id]);
    }

    public function bumpAuthVersion(int $id): void
    {
        $this->db->prepare('UPDATE users SET auth_version = auth_version + 1 WHERE id = ?')->execute([$id]);
    }

    public function updateProfile(int $id, string $name, ?string $locale): void
    {
        $this->db->prepare('UPDATE users SET name = ?, locale = ? WHERE id = ?')->execute([$name, $locale, $id]);
    }

    public function locale(int $id): ?string
    {
        $stmt = $this->db->prepare('SELECT locale FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $locale = $stmt->fetchColumn();

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    public function twoFactor(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT two_factor_secret, two_factor_recovery FROM users WHERE id = ? AND two_factor_enabled_at IS NOT NULL');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row && $row['two_factor_secret'] !== null ? ['secret' => (string) $row['two_factor_secret'], 'recovery' => (string) $row['two_factor_recovery']] : null;
    }

    public function setTwoFactor(int $id, ?string $encryptedSecret, ?string $recoveryJson): void
    {
        $this->db->prepare('UPDATE users SET two_factor_secret = ?, two_factor_recovery = ?, two_factor_enabled_at = ? WHERE id = ?')
            ->execute([$encryptedSecret, $recoveryJson, $encryptedSecret !== null ? $this->clock->now()->format('Y-m-d H:i:s') : null, $id]);
    }

    public function setRecovery(int $id, string $recoveryJson): void
    {
        $this->db->prepare('UPDATE users SET two_factor_recovery = ? WHERE id = ?')->execute([$recoveryJson, $id]);
    }

    public function anonymize(int $id, string $unusablePasswordHash): void
    {
        $this->db->prepare("UPDATE users SET name = ?, email = ?, password = ?, auth_version = auth_version + 1, locale = NULL,
                two_factor_secret = NULL, two_factor_recovery = NULL, two_factor_enabled_at = NULL, email_verified_at = NULL, deleted_at = ?
            WHERE id = ?")
            ->execute(['Deleted user', 'deleted-' . $id . '@deleted.invalid', $unusablePasswordHash, $this->clock->now()->format('Y-m-d H:i:s'), $id]);
    }

    private static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
