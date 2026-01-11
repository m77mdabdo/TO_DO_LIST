<?php

namespace Classes;

use PDO;

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 900;

    private ?array $user = null;

    public function __construct(private PDO $db)
    {
    }

    public function user(): ?array
    {
        $id = Session::get('user_id');

        if (!$id) {
            return null;
        }

        if ($this->user === null) {
            $stmt = $this->db->prepare('SELECT id, name, email FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $this->user = $stmt->fetch() ?: null;
        }

        return $this->user;
    }

    public function id(): ?int
    {
        return isset($this->user()['id']) ? (int) $this->user()['id'] : null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function tooManyAttempts(string $ip): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?');
        $stmt->execute([$ip, time() - self::LOCK_SECONDS]);

        return (int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public function attempt(string $email, string $password, string $ip): bool
    {
        $stmt = $this->db->prepare('SELECT id, password FROM users WHERE email = ?');
        $stmt->execute([mb_strtolower($email)]);
        $user = $stmt->fetch();

        // verify against a dummy hash too, so a missing email takes the same time
        $hash = $user['password'] ?? '$2y$10$yo7DWutMZbeStohJaVd1Ruj4hq721ZOMsDpHuNtHXwKA2CzjGLFsq';
        $ok = password_verify($password, $hash) && $user;

        if (!$ok) {
            $this->db->prepare('INSERT INTO login_attempts (ip, email, attempted_at) VALUES (?, ?, ?)')
                ->execute([$ip, mb_strtolower($email), time()]);
            return false;
        }

        $this->db->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);

        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->db->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        $this->login((int) $user['id']);

        return true;
    }

    public function emailTaken(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([mb_strtolower($email)]);

        return (bool) $stmt->fetchColumn();
    }

    public function register(string $name, string $email, string $password): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, mb_strtolower($email), password_hash($password, PASSWORD_DEFAULT), now()]);

        $id = (int) $this->db->lastInsertId();
        $this->login($id);

        return $id;
    }

    public function logout(): void
    {
        $this->user = null;
        Session::destroy();
    }

    private function login(int $id): void
    {
        Session::regenerate();
        Session::set('user_id', $id);
        $this->user = null;
    }
}
