<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\BoundaryRule;
use PDO;

class BoundaryRuleRepository
{
    public function __construct(
        private PDO $pdo,
    ) {}

    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM boundary_rules ORDER BY id ASC");
        return array_map(fn($row) => BoundaryRule::fromArray($row), $stmt->fetchAll());
    }

    public function getEnabled(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM boundary_rules WHERE is_enabled = 1 ORDER BY id ASC");
        return array_map(fn($row) => BoundaryRule::fromArray($row), $stmt->fetchAll());
    }

    public function findByType(string $ruleType): ?BoundaryRule
    {
        $stmt = $this->pdo->prepare("SELECT * FROM boundary_rules WHERE rule_type = ?");
        $stmt->execute([$ruleType]);
        $row = $stmt->fetch();
        return $row ? BoundaryRule::fromArray($row) : null;
    }

    public function findById(int $id): ?BoundaryRule
    {
        $stmt = $this->pdo->prepare("SELECT * FROM boundary_rules WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? BoundaryRule::fromArray($row) : null;
    }

    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = [];
        foreach (['rule_name', 'rule_value', 'is_enabled', 'description'] as $field) {
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
        $sql = "UPDATE boundary_rules SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO boundary_rules (rule_type, rule_name, rule_value, is_enabled, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['rule_type'],
            $data['rule_name'],
            $data['rule_value'],
            $data['is_enabled'] ?? 1,
            $data['description'] ?? '',
        ]);
        return (int)$this->pdo->lastInsertId();
    }
}
