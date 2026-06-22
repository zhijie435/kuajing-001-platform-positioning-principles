<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class FollowUpRepository
{
    public function __construct(
        private PDO $pdo,
    ) {}

    public function listByCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM follow_ups WHERE customer_id = ? ORDER BY created_at DESC");
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['customer_id'])) {
            $where[] = "customer_id = ?";
            $params[] = (int)$filters['customer_id'];
        }

        if (!empty($filters['type'])) {
            $where[] = "type = ?";
            $params[] = $filters['type'];
        }

        $whereClause = implode(' AND ', $where);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM follow_ups WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare("SELECT * FROM follow_ups WHERE {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?");
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
        $stmt = $this->pdo->prepare("SELECT * FROM follow_ups WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO follow_ups (customer_id, type, content, result, next_action, next_date, follow_up_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['customer_id'],
            $data['type'] ?? 'visit',
            $data['content'] ?? '',
            $data['result'] ?? '',
            $data['next_action'] ?? '',
            $data['next_date'] ?? null,
            $data['follow_up_by'] ?? 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function totalCount(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM follow_ups");
        return (int)$stmt->fetchColumn();
    }

    public function countByType(): array
    {
        $stmt = $this->pdo->query("SELECT type, COUNT(*) as count FROM follow_ups GROUP BY type");
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['type']] = (int)$row['count'];
        }
        return $result;
    }

    public function countToday(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM follow_ups WHERE date(created_at) = date('now')");
        return (int)$stmt->fetchColumn();
    }
}
