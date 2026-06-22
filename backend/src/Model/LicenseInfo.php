<?php

declare(strict_types=1);

namespace App\Model;

class LicenseInfo
{
    public function __construct(
        public int $id = 0,
        public string $licenseKey = '',
        public string $status = 'inactive',
        public ?string $activatedAt = null,
        public string $expiresAt = '',
        public int $maxUsers = 1,
        public string $domain = '*',
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)($data['id'] ?? 0),
            licenseKey: $data['license_key'] ?? '',
            status: $data['status'] ?? 'inactive',
            activatedAt: $data['activated_at'] ?? null,
            expiresAt: $data['expires_at'] ?? '',
            maxUsers: (int)($data['max_users'] ?? 1),
            domain: $data['domain'] ?? '*',
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'license_key' => $this->licenseKey,
            'status' => $this->status,
            'activated_at' => $this->activatedAt,
            'expires_at' => $this->expiresAt,
            'max_users' => $this->maxUsers,
            'domain' => $this->domain,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
