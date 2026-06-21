<?php
class PlatformGuard {
    const PLATFORM_TYPES = ['admin', 'sales', 'client'];
    const HEADER_PLATFORM = 'HTTP_X_PLATFORM_TYPE';

    const ERROR_PLATFORM_TYPE_MISSING = 4001;
    const ERROR_PLATFORM_TYPE_INVALID = 4002;
    const ERROR_PLATFORM_BOUNDARY_VIOLATION = 4003;
    const ERROR_PLATFORM_ENDPOINT_CONFIG_MISSING = 4004;

    private static $currentPlatform = null;
    private static $auditLog = [];
    private static $violations = [];

    private static $platformLabels = [
        'admin' => '管理端',
        'sales' => '销售端',
        'client' => '客户端'
    ];

    public static function validate() {
        $requestUri = self::getRequestUri();
        if (self::isPublicEndpoint($requestUri)) {
            return true;
        }

        if (!self::ensureEndpointConfigReady()) {
            self::recordViolation('endpoint_config_missing', [
                'uri' => $requestUri
            ]);
            throw new Exception(
                '平台端点边界配置缺失，请联系管理员检查系统配置',
                self::ERROR_PLATFORM_ENDPOINT_CONFIG_MISSING
            );
        }

        $platformType = isset($_SERVER[self::HEADER_PLATFORM])
            ? strtolower($_SERVER[self::HEADER_PLATFORM])
            : null;

        if (!$platformType) {
            self::recordViolation('platform_type_missing', [
                'header' => 'X-Platform-Type',
                'uri' => $requestUri
            ]);
            throw new Exception(
                '未能识别您的入口端类型。请通过正确的端登录（管理端/销售端/客户端），或联系管理员检查请求头标识。',
                self::ERROR_PLATFORM_TYPE_MISSING
            );
        }

        if (!in_array($platformType, self::PLATFORM_TYPES, true)) {
            self::recordViolation('platform_type_invalid', [
                'provided' => $platformType,
                'allowed' => self::PLATFORM_TYPES,
                'uri' => $requestUri
            ]);
            throw new Exception(
                sprintf('入口端标识无效，仅支持：管理端 / 销售端 / 客户端。请重新从正确的入口登录。'),
                self::ERROR_PLATFORM_TYPE_INVALID
            );
        }

        self::$currentPlatform = $platformType;

        if (!self::checkEndpointBoundary($requestUri, $platformType)) {
            $allowed = self::getAllowedEndpoints($platformType);
            self::recordViolation('platform_boundary_violation', [
                'platform' => $platformType,
                'platform_label' => self::$platformLabels[$platformType] ?? $platformType,
                'uri' => $requestUri,
                'allowed_modules' => $allowed
            ]);
            throw new Exception(
                sprintf(
                    '当前入口（%s）无权访问该功能。%s端可用模块：%s。如需访问请切换至对应入口端登录。',
                    self::$platformLabels[$platformType] ?? $platformType,
                    self::$platformLabels[$platformType] ?? $platformType,
                    implode('、', $allowed)
                ),
                self::ERROR_PLATFORM_BOUNDARY_VIOLATION
            );
        }

        self::audit('platform_access', [
            'platform' => $platformType,
            'uri' => $requestUri,
            'ip' => self::getClientIp()
        ]);

        return true;
    }

    private static function getRequestUri() {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    private static function ensureEndpointConfigReady() {
        return defined('PLATFORM_ENDPOINTS') && is_array(PLATFORM_ENDPOINTS);
    }

    private static function isPublicEndpoint($uri) {
        return (bool)preg_match('#^/api/auth/(login|platform-info|refresh)$#', $uri)
            || (bool)preg_match('#^/api/?$#', $uri);
    }

    private static function getAllowedEndpoints($platformType) {
        if (!self::ensureEndpointConfigReady()) {
            return [];
        }
        return PLATFORM_ENDPOINTS[$platformType] ?? [];
    }

    private static function checkEndpointBoundary($uri, $platformType) {
        $allowedEndpoints = self::getAllowedEndpoints($platformType);

        $uri = preg_replace('#^/api/#', '', $uri);
        $uriParts = explode('/', $uri);
        $module = $uriParts[0] ?? '';
        $subModule = $uriParts[1] ?? '';

        if ($module === 'auth') {
            return true;
        }

        if (empty($allowedEndpoints)) {
            self::recordViolation('platform_endpoints_empty', [
                'platform' => $platformType,
                'uri' => $uri
            ]);
            return false;
        }

        if ($module === 'admin') {
            if ($platformType !== 'admin') {
                return false;
            }
            return $subModule === '' || in_array($subModule, $allowedEndpoints, true);
        }

        if ($module === 'client') {
            if ($platformType !== 'client') {
                return false;
            }
            return $subModule === '' || in_array($subModule, $allowedEndpoints, true);
        }

        return in_array($module, $allowedEndpoints, true) || empty($module);
    }

    public static function getCurrentPlatform() {
        return self::$currentPlatform;
    }

    public static function getPlatformInfo() {
        return [
            'name' => defined('PLATFORM_NAME') ? PLATFORM_NAME : 'Unknown',
            'version' => defined('PLATFORM_VERSION') ? PLATFORM_VERSION : '0.0.0',
            'type' => defined('PLATFORM_TYPE') ? PLATFORM_TYPE : 'unknown',
            'platforms' => self::PLATFORM_TYPES,
            'platform_labels' => self::$platformLabels,
            'current' => self::$currentPlatform,
            'endpoints' => self::ensureEndpointConfigReady() ? PLATFORM_ENDPOINTS : []
        ];
    }

    private static function audit($action, $data) {
        self::$auditLog[] = [
            'action' => $action,
            'data' => $data,
            'time' => date('Y-m-d H:i:s')
        ];
    }

    public static function getAuditLog() {
        return self::$auditLog;
    }

    private static function recordViolation($type, $data) {
        self::$violations[] = [
            'type' => $type,
            'data' => $data,
            'time' => date('Y-m-d H:i:s'),
            'uri' => self::getRequestUri()
        ];
    }

    public static function getViolations() {
        return self::$violations;
    }

    public static function getPlatformLabels() {
        return self::$platformLabels;
    }

    private static function getClientIp() {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}
