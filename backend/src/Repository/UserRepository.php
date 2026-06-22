<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class UserRepository
{
    public function __construct(
        private PDO $pdo,
    ) {}

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login_at = datetime('now') WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT id, username, display_name, role, status, last_login_at, created_at FROM users ORDER BY id ASC");
        return $stmt->fetchAll();
    }
}
