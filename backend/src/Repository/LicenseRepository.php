<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\LicenseInfo;
use PDO;

class LicenseRepository
{
    public function __construct(
        private PDO $pdo,
    ) {}

    public function getActiveLicense(): ?LicenseInfo
    {
        $stmt = $this->pdo->query("SELECT * FROM licenses WHERE status = 'active' ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch();
        return $row ? LicenseInfo::fromArray($row) : null;
    }

    public function findByKey(string $licenseKey): ?LicenseInfo
    {
        $stmt = $this->pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
        $stmt->execute([$licenseKey]);
        $row = $stmt->fetch();
        return $row ? LicenseInfo::fromArray($row) : null;
    }

    public function findById(int $id): ?LicenseInfo
    {
        $stmt = $this->pdo->prepare("SELECT * FROM licenses WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? LicenseInfo::fromArray($row) : null;
    }

    public function activate(string $licenseKey): bool
    {
        $stmt = $this->pdo->prepare("UPDATE licenses SET status = 'active', activated_at = datetime('now'), updated_at = datetime('now') WHERE license_key = ? AND status = 'inactive'");
        $stmt->execute([$licenseKey]);
        return $stmt->rowCount() > 0;
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM licenses ORDER BY id DESC");
        return array_map(fn($row) => LicenseInfo::fromArray($row), $stmt->fetchAll());
    }
}
