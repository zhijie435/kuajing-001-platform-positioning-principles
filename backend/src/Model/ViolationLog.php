<?php

declare(strict_types=1);

namespace App\Model;

class ViolationLog
{
    public function __construct(
        public int $id = 0,
        public ?int $ruleId = null,
        public string $ruleType = '',
        public string $violationDetail = '',
        public string $clientIp = '',
        public string $requestPath = '',
        public ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)($data['id'] ?? 0),
            ruleId: isset($data['rule_id']) ? (int)$data['rule_id'] : null,
            ruleType: $data['rule_type'] ?? '',
            violationDetail: $data['violation_detail'] ?? '',
            clientIp: $data['client_ip'] ?? '',
            requestPath: $data['request_path'] ?? '',
            createdAt: $data['created_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rule_id' => $this->ruleId,
            'rule_type' => $this->ruleType,
            'violation_detail' => $this->violationDetail,
            'client_ip' => $this->clientIp,
            'request_path' => $this->requestPath,
            'created_at' => $this->createdAt,
        ];
    }
}
