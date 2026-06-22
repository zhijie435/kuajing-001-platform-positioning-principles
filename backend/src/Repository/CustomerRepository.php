<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class CustomerRepository
{
    public function __construct(
        private PDO $pdo,
    ) {}

    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(name LIKE ? OR company LIKE ? OR phone LIKE ? OR email LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['level'])) {
            $where[] = "level = ?";
            $params[] = $filters['level'];
        }

        if (!empty($filters['source'])) {
            $where[] = "source = ?";
            $params[] = $filters['source'];
        }

        $whereClause = implode(' AND ', $where);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM customers WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO customers (name, company, phone, email, source, industry, status, level, assigned_to, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['company'] ?? '',
            $data['phone'] ?? '',
            $data['email'] ?? '',
            $data['source'] ?? '',
            $data['industry'] ?? '',
            $data['status'] ?? 'potential',
            $data['level'] ?? 'C',
            $data['assigned_to'] ?? 0,
            $data['remark'] ?? '',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = [];
        foreach (['name', 'company', 'phone', 'email', 'source', 'industry', 'status', 'level', 'assigned_to', 'remark'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        if (empty($sets)) {
            return false;
        }
        $sets[] = "updated_at = datetime('now')";
        $params[] = $id;
        $sql = "UPDATE customers SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function countByStatus(): array
    {
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM customers GROUP BY status");
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int)$row['count'];
        }
        return $result;
    }

    public function countByLevel(): array
    {
        $stmt = $this->pdo->query("SELECT level, COUNT(*) as count FROM customers GROUP BY level");
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['level']] = (int)$row['count'];
        }
        return $result;
    }

    public function totalCount(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM customers");
        return (int)$stmt->fetchColumn();
    }
}
