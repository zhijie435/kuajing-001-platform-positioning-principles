<?php

declare(strict_types=1);

namespace App\Model;

class BoundaryRule
{
    public function __construct(
        public int $id = 0,
        public string $ruleType = '',
        public string $ruleName = '',
        public string $ruleValue = '',
        public bool $isEnabled = true,
        public string $description = '',
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)($data['id'] ?? 0),
            ruleType: $data['rule_type'] ?? '',
            ruleName: $data['rule_name'] ?? '',
            ruleValue: $data['rule_value'] ?? '',
            isEnabled: (bool)($data['is_enabled'] ?? true),
            description: $data['description'] ?? '',
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rule_type' => $this->ruleType,
            'rule_name' => $this->ruleName,
            'rule_value' => $this->ruleValue,
            'is_enabled' => $this->isEnabled,
            'description' => $this->description,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
