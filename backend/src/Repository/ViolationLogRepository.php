<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ViolationLog;
use PDO;

class ViolationLogRepository
{
    public function __construct(
        private PDO $pdo,
    ) {}

    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO violation_logs (rule_id, rule_type, violation_detail, client_ip, request_path) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['rule_id'] ?? null,
            $data['rule_type'] ?? '',
            $data['violation_detail'] ?? '',
            $data['client_ip'] ?? '',
            $data['request_path'] ?? '',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getPaginated(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $countStmt = $this->pdo->query("SELECT COUNT(*) FROM violation_logs");
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT * FROM violation_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$perPage, $offset]);
        $items = array_map(fn($row) => ViolationLog::fromArray($row), $stmt->fetchAll());

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    public function getRecent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM violation_logs ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return array_map(fn($row) => ViolationLog::fromArray($row), $stmt->fetchAll());
    }
}
