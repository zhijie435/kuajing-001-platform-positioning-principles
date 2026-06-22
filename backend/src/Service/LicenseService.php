<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LicenseRepository;
use App\Model\LicenseInfo;

class LicenseService
{
    public function __construct(
        private LicenseRepository $licenseRepo,
    ) {}

    public function getActiveLicense(): ?LicenseInfo
    {
        return $this->licenseRepo->getActiveLicense();
    }

    public function isLicenseValid(): bool
    {
        $license = $this->getActiveLicense();
        if ($license === null) {
            return false;
        }
        if ($license->status !== 'active') {
            return false;
        }
        $expiresAt = new \DateTime($license->expiresAt);
        $now = new \DateTime();
        return $expiresAt > $now;
    }

    public function activateLicense(string $licenseKey): array
    {
        $license = $this->licenseRepo->findByKey($licenseKey);
        if ($license === null) {
            return ['success' => false, 'message' => 'License key not found'];
        }
        if ($license->status === 'active') {
            return ['success' => false, 'message' => 'License already activated'];
        }
        $result = $this->licenseRepo->activate($licenseKey);
        if (!$result) {
            return ['success' => false, 'message' => 'Activation failed'];
        }
        return ['success' => true, 'message' => 'License activated successfully'];
    }

    public function getDaysRemaining(): int
    {
        $license = $this->getActiveLicense();
        if ($license === null) {
            return 0;
        }
        $expiresAt = new \DateTime($license->expiresAt);
        $now = new \DateTime();
        $diff = $now->diff($expiresAt);
        return $diff->invert ? 0 : $diff->days;
    }

    public function getLicenseInfo(): ?array
    {
        $license = $this->getActiveLicense();
        if ($license === null) {
            return null;
        }
        return array_merge($license->toArray(), [
            'days_remaining' => $this->getDaysRemaining(),
            'is_valid' => $this->isLicenseValid(),
        ]);
    }
}
