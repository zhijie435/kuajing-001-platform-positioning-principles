<?php

declare(strict_types=1);

namespace App\Guards;

use Psr\Http\Message\ServerRequestInterface as Request;

class PlatformGuard extends AbstractGuard
{
    protected string $name = 'platform';
    protected int $priority = 100;

    public const CODE_PLATFORM_INVALID = 4001;
    public const CODE_PLATFORM_NOT_ALLOWED = 4002;
    public const CODE_PLATFORM_SIGNATURE_INVALID = 4003;
    public const CODE_PLATFORM_TIMESTAMP_EXPIRED = 4004;

    public const PLATFORM_PC = 'pc';
    public const PLATFORM_MOBILE = 'mobile';
    public const PLATFORM_ADMIN = 'admin';
    public const PLATFORM_MINIAPP = 'miniapp';

    public const ALLOWED_PLATFORMS = [
        self::PLATFORM_PC,
        self::PLATFORM_MOBILE,
        self::PLATFORM_ADMIN,
        self::PLATFORM_MINIAPP,
    ];

    private array $platformSecrets = [];

    public function __construct(array $platformSecrets = [])
    {
        $this->platformSecrets = $platformSecrets ?: [
            self::PLATFORM_PC => $_ENV['PLATFORM_PC_SECRET'] ?? 'pc-secret-key',
            self::PLATFORM_MOBILE => $_ENV['PLATFORM_MOBILE_SECRET'] ?? 'mobile-secret-key',
            self::PLATFORM_ADMIN => $_ENV['PLATFORM_ADMIN_SECRET'] ?? 'admin-secret-key',
            self::PLATFORM_MINIAPP => $_ENV['PLATFORM_MINIAPP_SECRET'] ?? 'miniapp-secret-key',
        ];
    }

    public function verify(Request $request): GuardResult
    {
        $platform = $this->getHeader($request, 'X-Platform', '');
        $timestamp = $this->getHeader($request, 'X-Timestamp', '');
        $signature = $this->getHeader($request, 'X-Signature', '');

        if (empty($platform)) {
            return GuardResult::fail(
                self::CODE_PLATFORM_INVALID,
                '缺少平台标识',
                [],
                $this->name
            );
        }

        if (!in_array($platform, self::ALLOWED_PLATFORMS, true)) {
            return GuardResult::fail(
                self::CODE_PLATFORM_NOT_ALLOWED,
                '不支持的平台类型: ' . $platform,
                ['allowed_platforms' => self::ALLOWED_PLATFORMS],
                $this->name
            );
        }

        $path = $request->getUri()->getPath();
        if ($this->isPublicPath($path)) {
            return GuardResult::pass([
                'platform' => $platform,
                'platform_verified' => true,
            ], $this->name);
        }

        if (empty($timestamp) || empty($signature)) {
            return GuardResult::fail(
                self::CODE_PLATFORM_SIGNATURE_INVALID,
                '缺少签名参数',
                [],
                $this->name
            );
        }

        $timestampInt = (int)$timestamp;
        $now = time();
        $diff = abs($now - $timestampInt);
        $maxDiff = (int)($_ENV['PLATFORM_SIGNATURE_EXPIRE'] ?? 300);
        if ($diff > $maxDiff) {
            return GuardResult::fail(
                self::CODE_PLATFORM_TIMESTAMP_EXPIRED,
                '请求时间戳已过期',
                ['server_time' => $now, 'request_time' => $timestampInt],
                $this->name
            );
        }

        $secret = $this->platformSecrets[$platform] ?? '';
        if (empty($secret)) {
            return GuardResult::fail(
                self::CODE_PLATFORM_NOT_ALLOWED,
                '平台密钥未配置',
                [],
                $this->name
            );
        }

        $method = $request->getMethod();
        $expectedSignature = $this->generateSignature($method, $path, $timestamp, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return GuardResult::fail(
                self::CODE_PLATFORM_SIGNATURE_INVALID,
                '平台签名验证失败',
                [],
                $this->name
            );
        }

        return GuardResult::pass([
            'platform' => $platform,
            'platform_verified' => true,
            'timestamp' => $timestampInt,
        ], $this->name);
    }

    private function isPublicPath(string $path): bool
    {
        $publicPaths = [
            '/api/health',
            '/api/auth/login',
            '/api/guard/verify',
            '/api/guard/info',
        ];

        foreach ($publicPaths as $publicPath) {
            if (str_starts_with($path, $publicPath)) {
                return true;
            }
        }

        return false;
    }

    private function generateSignature(string $method, string $path, string $timestamp, string $secret): string
    {
        $payload = strtoupper($method) . "\n" . $path . "\n" . $timestamp;
        return hash_hmac('sha256', $payload, $secret);
    }
}
