<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\ConnectionFactory;
use App\Database\Migrator;
use PDO;

final class Database
{
    /** A fresh in-memory SQLite database with the app's schema. */
    public static function fresh(): PDO
    {
        $pdo = ConnectionFactory::sqlite(':memory:');
        (new Migrator($pdo, BASE_PATH . '/database'))->migrate();

        return $pdo;
    }

    public static function user(PDO $pdo, string $email = 'sara@example.com'): int
    {
        $pdo->prepare('INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, ?)')
            ->execute(['Sara', $email, password_hash('secret123', PASSWORD_DEFAULT), '2026-01-01 10:00:00']);

        return (int) $pdo->lastInsertId();
    }
}
