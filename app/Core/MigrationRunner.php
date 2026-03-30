<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Core;

use PDO;

class MigrationRunner
{
    public function __construct(
        private readonly Database $database
    ) {
    }

    public function run(): void
    {
        $pdo = $this->database->connect();
        $this->createMigrationsTable($pdo);

        $files = glob(V2_MIGRATIONS . '/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE name = ?');
            $stmt->execute([$name]);

            if ((int) $stmt->fetchColumn() > 0) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException('Unable to read migration ' . $name);
            }

            $pdo->exec($sql);
            $insert = $pdo->prepare('INSERT INTO migrations (name, ran_at) VALUES (?, ?)');
            $insert->execute([$name, date('c')]);
        }
    }

    public function importLegacyUsers(string $legacyDatabasePath): int
    {
        if (!is_file($legacyDatabasePath)) {
            return 0;
        }

        $legacy = new PDO('sqlite:' . $legacyDatabasePath);
        $legacy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $legacy->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $sourceUsers = $legacy->query('SELECT id, name, email, password, role, created_at FROM users ORDER BY id ASC')->fetchAll();
        if (!$sourceUsers) {
            return 0;
        }

        $pdo = $this->database->connect();
        $imported = 0;

        foreach ($sourceUsers as $user) {
            $role = $user['role'] === 'admin' ? 'admin' : 'creator';
            $existing = $pdo->prepare('SELECT id FROM users WHERE legacy_user_id = ? OR email = ? LIMIT 1');
            $existing->execute([(int) $user['id'], $user['email']]);
            $found = $existing->fetch();

            if ($found) {
                $update = $pdo->prepare('UPDATE users SET legacy_user_id = ?, name = ?, role = ?, updated_at = ? WHERE id = ?');
                $update->execute([(int) $user['id'], $user['name'], $role, date('c'), (int) $found['id']]);
                continue;
            }

            $insert = $pdo->prepare('INSERT INTO users (legacy_user_id, name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([(int) $user['id'], $user['name'], $user['email'], $user['password'], $role, $user['created_at'] ?: date('c'), date('c')]);
            $imported++;
        }

        return $imported;
    }

    private function createMigrationsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, ran_at TEXT NOT NULL)');
    }
}