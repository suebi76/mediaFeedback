<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Models;

class User extends BaseModel
{
    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function all(): array
    {
        $stmt = $this->pdo()->query('SELECT id, name, email, role, created_at, updated_at FROM users ORDER BY created_at DESC, id DESC');
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $email = $this->normalizeEmail($email);
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function createOrUpdateAdmin(string $name, string $email, string $password): int
    {
        $email = $this->normalizeEmail($email);
        $existing = $this->findByEmail($email);
        $hash = password_hash($password, PASSWORD_BCRYPT);

        if ($existing) {
            $stmt = $this->pdo()->prepare('UPDATE users SET name = ?, password_hash = ?, role = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$name, $hash, 'admin', date('c'), (int) $existing['id']]);
            return (int) $existing['id'];
        }

        $stmt = $this->pdo()->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $email, $hash, 'admin', date('c'), date('c')]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function create(string $name, string $email, string $password, string $role): int
    {
        $email = $this->normalizeEmail($email);
        $stmt = $this->pdo()->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $name,
            $email,
            password_hash($password, PASSWORD_BCRYPT),
            $role,
            date('c'),
            date('c'),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function countAdmins(): int
    {
        $stmt = $this->pdo()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        return (int) $stmt->fetchColumn();
    }
}
