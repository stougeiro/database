<?php declare(strict_types=1);

namespace Tests\Stubs;

use PDO;

final class Schema
{
    public static function users(): string
    {
        return 'users';
    }

    public static function createUsers(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                email TEXT,
                age INTEGER,
                active INTEGER DEFAULT 1,
                deleted_at TEXT
            )',
        );
    }

    public static function createPosts(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                title TEXT NOT NULL,
                views INTEGER DEFAULT 0
            )',
        );
    }

    public static function createDocsFts(PDO $pdo): void
    {
        $pdo->exec('CREATE VIRTUAL TABLE docs USING fts5(title, body)');
    }

    /** @param array<int, array<string, mixed>> $rows
     */
    public static function seedUsers(PDO $pdo, array $rows): void
    {
        self::seedInto($pdo, 'users', $rows);
    }

    /** @param array<int, array<string, mixed>> $rows
     */
    public static function seedPosts(PDO $pdo, array $rows): void
    {
        self::seedInto($pdo, 'posts', $rows);
    }

    /** @param array<int, array<string, mixed>> $rows
     */
    public static function seedDocsFts(PDO $pdo, array $rows): void
    {
        self::seedInto($pdo, 'docs', $rows);
    }

    /** @param array<int, array<string, mixed>> $rows
     */
    private static function seedInto(PDO $pdo, string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $columns = implode(', ', array_keys($row));
            $placeholders = implode(', ', array_fill(0, count($row), '?'));

            $statement = $pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
            $statement->execute(array_values($row));
        }
    }
}
