<?php

declare(strict_types=1);

namespace App\Guards;

use App\Models\License;
use App\Models\User;
use Psr\Http\Message\ServerRequestInterface as Request;

class CommercialGuard extends AbstractGuard
{
    protected string $name = 'commercial';
    protected int $priority = 200;

    public const CODE_LICENSE_EMPTY = 4101;
    public const CODE_LICENSE_INVALID = 4102;
    public const CODE_LICENSE_EXPIRED = 4103;
    public const CODE_LICENSE_SUSPENDED = 4104;
    public const CODE_USERS_EXCEEDED = 4105;
    public const CODE_CUSTOMERS_EXCEEDED = 4106;
    public const CODE_FEATURE_NOT_ALLOWED = 4107;
    public const CODE_API_RATE_LIMIT = 4108;

    public const LICENSE_TYPE_TRIAL = 'trial';
    public const LICENSE_TYPE_STANDARD = 'standard';
    public const LICENSE_TYPE_PROFESSIONAL = 'professional';
    public const LICENSE_TYPE_ENTERPRISE = 'enterprise';

    public const LICENSE_TYPES = [
        self::LICENSE_TYPE_TRIAL,
        self::LICENSE_TYPE_STANDARD,
        self::LICENSE_TYPE_PROFESSIONAL,
        self::LICENSE_TYPE_ENTERPRISE,
    ];

    public function verify(Request $request): GuardResult
    {
        $path = $request->getUri()->getPath();

        if ($this->isPublicPath($path)) {
            return GuardResult::pass([
                'commercial_verified' => true,
                'license_type' => 'public',
            ], $this->name);
        }

        $licenseKey = $this->getLicenseKey($request);
        if (empty($licenseKey)) {
            return GuardResult::fail(
                self::CODE_LICENSE_EMPTY,
                '缺少许可证信息',
                [],
                $this->name
            );
        }

        try {
            $license = License::byKey($licenseKey)->first();
        } catch (\Exception $e) {
            return GuardResult::pass([
                'commercial_verified' => true,
                'license_type' => 'fallback',
                'note' => 'database_unavailable',
            ], $this->name);
        }

        if (!$license) {
            return GuardResult::fail(
                self::CODE_LICENSE_INVALID,
                '许可证无效',
                [],
                $this->name
            );
        }

        if ($license->status !== 'active') {
            return GuardResult::fail(
                self::CODE_LICENSE_SUSPENDED,
                '许可证已被停用',
                ['status' => $license->status],
                $this->name
            );
        }

        if ($license->expired_at && $license->expired_at->isPast()) {
            return GuardResult::fail(
                self::CODE_LICENSE_EXPIRED,
                '许可证已过期',
                ['expired_at' => $license->expired_at->toDateTimeString()],
                $this->name
            );
        }

        $userId = $this->getUserId($request);
        if ($userId) {
            $userCount = $this->getUserCount($license->id);
            if ($license->max_users > 0 && $userCount > $license->max_users) {
                return GuardResult::fail(
                    self::CODE_USERS_EXCEEDED,
                    '用户数量超出许可证限制',
                    [
                        'current' => $userCount,
                        'limit' => $license->max_users,
                    ],
                    $this->name
                );
            }
        }

        $platform = $this->getHeader($request, 'X-Platform', '');
        if (!$this->isPlatformAllowed($license, $platform)) {
            return GuardResult::fail(
                self::CODE_FEATURE_NOT_ALLOWED,
                '当前许可证不支持该平台',
                ['platform' => $platform],
                $this->name
            );
        }

        return GuardResult::pass([
            'commercial_verified' => true,
            'license_id' => $license->id,
            'license_type' => $license->license_type,
            'license_key' => $licenseKey,
            'max_users' => $license->max_users,
            'max_customers' => $license->max_customers,
            'max_follows_per_day' => $license->max_follows_per_day,
            'expired_at' => $license->expired_at ? $license->expired_at->toDateTimeString() : null,
        ], $this->name);
    }

    private function isPublicPath(string $path): bool
    {
        $publicPaths = [
            '/api/health',
            '/api/auth/login',
            '/api/guard/verify',
            '/api/guard/info',
            '/api/admin/license/activate',
        ];

        foreach ($publicPaths as $publicPath) {
            if (str_starts_with($path, $publicPath)) {
                return true;
            }
        }

        return false;
    }

    private function getLicenseKey(Request $request): string
    {
        $licenseKey = $this->getHeader($request, 'X-License', '');
        if (!empty($licenseKey)) {
            return $licenseKey;
        }

        $authHeader = $this->getHeader($request, 'Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            try {
                $decoded = \Firebase\JWT\JWT::decode(
                    $token,
                    new \Firebase\JWT\Key($_ENV['JWT_SECRET'] ?? 'your-jwt-secret',
                    ['HS256']
                );
                return $decoded->license_key ?? '';
            } catch (\Exception $e) {
                // ignore
            }
        }

        return '';
    }

    private function getUserId(Request $request): ?int
    {
        $authHeader = $this->getHeader($request, 'Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            try {
                $decoded = \Firebase\JWT\JWT::decode(
                    $token,
                    new \Firebase\JWT\Key($_ENV['JWT_SECRET'] ?? 'your-jwt-secret',
                    ['HS256']
                );
                return $decoded->user_id ?? null;
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    private function getUserCount(int $licenseId): int
    {
        try {
            return User::where('license_id', $licenseId)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function isPlatformAllowed(License $license, string $platform): bool
    {
        if (empty($platform)) {
            return true;
        }

        $platformPermissions = [
            self::LICENSE_TYPE_TRIAL => ['pc', 'mobile'],
            self::LICENSE_TYPE_STANDARD => ['pc', 'mobile', 'miniapp'],
            self::LICENSE_TYPE_PROFESSIONAL => ['pc', 'mobile', 'miniapp', 'admin'],
            self::LICENSE_TYPE_ENTERPRISE => ['pc', 'mobile', 'miniapp', 'admin'],
        ];

        $allowed = $platformPermissions[$license->license_type] ?? [];
        return in_array($platform, $allowed, true);
    }
}
