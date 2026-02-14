<?php

declare(strict_types=1);

namespace App\Auth;

use App\Support\Clock;
use PDO;

final class PdoUserRepository implements UserRepository
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function find(int $id): ?User
    {
        $stmt = $this->db->prepare('SELECT id, name, email FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([self::normalize($email)]);

        return (bool) $stmt->fetchColumn();
    }

    public function credentials(string $email): ?Credentials
    {
        $stmt = $this->db->prepare('SELECT id, password FROM users WHERE email = ?');
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

    private static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
