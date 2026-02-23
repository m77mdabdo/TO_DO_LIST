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
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([mb_strtolower(trim($email))]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function create(string $name, string $email, string $passwordHash): User
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $name,
            mb_strtolower(trim($email)),
            $passwordHash,
            $this->clock->now()->format('Y-m-d H:i:s'),
        ]);

        return $this->find((int) $this->db->lastInsertId());
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$passwordHash, $id]);
    }
}
