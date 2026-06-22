<?php

declare(strict_types=1);

namespace App\Guards;

use App\Models\RedLineConfig;
use App\Models\AuditLog;
use Psr\Http\Message\ServerRequestInterface as Request;

class RedLineGuard extends AbstractGuard
{
    protected string $name = 'redline';
    protected int $priority = 300;

    public const CODE_REDLINE_DAILY_API_LIMIT = 4201;
    public const CODE_REDLINE_SENSITIVE_OPERATION = 4202;
    public const CODE_REDLINE_DATA_LIMIT = 4203;
    public const CODE_REDLINE_RISK_USER = 4204;
    public const CODE_REDLINE_ABNORMAL_BEHAVIOR = 4205;
    public const CODE_REDLINE_BULK_OPERATION = 4206;

    public const REDLINE_KEYS = [
        'daily_api_limit' => [
            'type' => 'integer',
            'default' => 1000,
            'description' => '每日API调用次数限制',
        ],
        'daily_follow_limit' => [
            'type' => 'integer',
            'default' => 100,
            'description' => '每日跟进记录数量限制',
        ],
        'daily_customer_create_limit' => [
            'type' => 'integer',
            'default' => 50,
            'description' => '每日新增客户数量限制',
        ],
        'bulk_operation_max_size' => [
            'type' => 'integer',
            'default' => 100,
            'description' => '批量操作最大数量限制',
        ],
        'sensitive_module_enabled' => [
            'type' => 'boolean',
            'default' => true,
            'description' => '是否启用敏感操作校验',
        ],
        'risk_detection_enabled' => [
            'type' => 'boolean',
            'default' => true,
            'description' => '是否启用风险检测',
        ],
        'abnormal_behavior_detection' => [
            'type' => 'boolean',
            'default' => true,
            'description' => '是否启用异常行为检测',
        ],
        'request_rate_per_minute' => [
            'type' => 'integer',
            'default' => 60,
            'description' => '每分钟请求速率限制',
        ],
    ];

    private array $configCache = [];

    public function verify(Request $request): GuardResult
    {
        $path = $request->getUri()->getPath();
        $platform = $this->getHeader($request, 'X-Platform', '');

        if ($this->isPublicPath($path)) {
            return GuardResult::pass([
                'redline_verified' => true,
                'check_type' => 'bypass',
            ], $this->name);
        }

        $config = $this->getRedLineConfig($platform);

        $rateResult = $this->checkRequestRate($request, $config);
        if ($rateResult->isFailed()) {
            return $rateResult;
        }

        $dailyResult = $this->checkDailyLimit($request, $config);
        if ($dailyResult->isFailed()) {
            return $dailyResult;
        }

        $sensitiveResult = $this->checkSensitiveOperation($request, $config);
        if ($sensitiveResult->isFailed()) {
            return $sensitiveResult;
        }

        $behaviorResult = $this->checkAbnormalBehavior($request, $config);
        if ($behaviorResult->isFailed()) {
            return $behaviorResult;
        }

        return GuardResult::pass([
            'redline_verified' => true,
            'platform' => $platform,
            'check_items' => ['rate', 'daily', 'sensitive', 'behavior'],
        ], $this->name);
    }

    private function isPublicPath(string $path): bool
    {
        $publicPaths = [
            '/api/health',
            '/api/auth/login',
            '/api/guard/verify',
            '/api/guard/info',
            '/api/admin/redline/config',
        ];

        foreach ($publicPaths as $publicPath) {
            if (str_starts_with($path, $publicPath)) {
                return true;
            }
        }

        return false;
    }

    private function getRedLineConfig(string $platform): array
    {
        if (isset($this->configCache[$platform])) {
            return $this->configCache[$platform];
        }

        $config = [];
        foreach (self::REDLINE_KEYS as $key => $meta) {
            $config[$key] = $meta['default'];
        }

        try {
            $dbConfigs = RedLineConfig::getAllByPlatform($platform);
            foreach ($dbConfigs as $key => $value) {
                if (isset($config[$key])) {
                    $config[$key] = $value;
                }
            }

            $allConfigs = RedLineConfig::getAllByPlatform('all');
            foreach ($allConfigs as $key => $value) {
                if (isset($config[$key]) && !isset($dbConfigs[$key])) {
                    $config[$key] = $value;
                }
            }
        } catch (\Exception $e) {
            // 使用默认配置
        }

        $this->configCache[$platform] = $config;
        return $config;
    }

    private function checkRequestRate(Request $request, array $config): GuardResult
    {
        if (empty($config['abnormal_behavior_detection'])) {
            return GuardResult::pass([], $this->name);
        }

        $limit = $config['request_rate_per_minute'] ?? 60;
        $ip = $this->getClientIp($request);
        $path = $request->getUri()->getPath();

        try {
            $minute = date('Y-m-d H:i');
            $count = AuditLog::where('ip', $ip)
                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 minute')))
                ->count();

            if ($count >= $limit) {
                return GuardResult::fail(
                    self::CODE_REDLINE_ABNORMAL_BEHAVIOR,
                    '请求频率超出限制',
                    [
                        'current' => $count,
                        'limit' => $limit,
                        'window' => '1 minute',
                    ],
                    $this->name
                );
            }
        } catch (\Exception $e) {
            // 数据库不可用时跳过
        }

        return GuardResult::pass([], $this->name);
    }

    private function checkDailyLimit(Request $request, array $config): GuardResult
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        if (str_contains($path, '/follow') && $method === 'POST') {
            $limit = $config['daily_follow_limit'] ?? 100;
            $count = $this->getTodayCount('follow_create');
            if ($count >= $limit) {
                return GuardResult::fail(
                    self::CODE_REDLINE_DAILY_API_LIMIT,
                    '每日跟进数量已达上限',
                    [
                        'current' => $count,
                        'limit' => $limit,
                        'type' => 'follow',
                    ],
                    $this->name
                );
            }
        }

        if (str_contains($path, '/customer') && $method === 'POST') {
            $limit = $config['daily_customer_create_limit'] ?? 50;
            $count = $this->getTodayCount('customer_create');
            if ($count >= $limit) {
                return GuardResult::fail(
                    self::CODE_REDLINE_DAILY_API_LIMIT,
                    '每日新增客户数量已达上限',
                    [
                        'current' => $count,
                        'limit' => $limit,
                        'type' => 'customer',
                    ],
                    $this->name
                );
            }
        }

        return GuardResult::pass([], $this->name);
    }

    private function checkSensitiveOperation(Request $request, array $config): GuardResult
    {
        if (empty($config['sensitive_module_enabled'])) {
            return GuardResult::pass([], $this->name);
        }

        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        $sensitivePatterns = [
            ['path' => '/api/admin/license/delete', 'method' => 'DELETE'],
            ['path' => '/api/customer/', 'method' => 'DELETE'],
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($path, $pattern['path']) && $method === $pattern['method']) {
                return GuardResult::pass([
                    'sensitive' => true,
                    'operation' => $pattern['path'],
                ], $this->name);
            }
        }

        return GuardResult::pass([
            'sensitive' => false,
        ], $this->name);
    }

    private function checkAbnormalBehavior(Request $request, array $config): GuardResult
    {
        if (empty($config['risk_detection_enabled'])) {
            return GuardResult::pass([], $this->name);
        }

        return GuardResult::pass([], $this->name);
    }

    private function getTodayCount(string $type): int
    {
        try {
            $today = date('Y-m-d');
            return AuditLog::whereDate('created_at', $today)
                ->where('module', $type)
                ->where('status', 'success')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
