<?php
class PlatformGuard {
    const PLATFORM_TYPES = ['admin', 'sales', 'client'];
    const HEADER_PLATFORM = 'HTTP_X_PLATFORM_TYPE';

    private static $currentPlatform = null;
    private static $auditLog = [];

    public static function validate() {
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (self::isPublicEndpoint($requestUri)) {
            return true;
        }

        $platformType = isset($_SERVER[self::HEADER_PLATFORM])
            ? strtolower($_SERVER[self::HEADER_PLATFORM])
            : null;

        if (!$platformType) {
            throw new Exception('平台类型标识缺失，请通过 X-Platform-Type 指定入口端', 4001);
        }

        if (!in_array($platformType, self::PLATFORM_TYPES)) {
            throw new Exception(sprintf(
                '非法平台类型: %s，仅允许: %s',
                $platformType,
                implode('/', self::PLATFORM_TYPES)
            ), 4002);
        }

        self::$currentPlatform = $platformType;

        if (!self::checkEndpointBoundary($requestUri, $platformType)) {
            throw new Exception(sprintf(
                '平台定位越界: [%s]端 无权访问该接口',
                $platformType
            ), 4003);
        }

        self::audit('platform_access', [
            'platform' => $platformType,
            'uri' => $requestUri,
            'ip' => self::getClientIp()
        ]);

        return true;
    }

    private static function isPublicEndpoint($uri) {
        $publicPatterns = [
            '#^/api/auth/login$#',
            '#^/api/auth/platform-info$#',
            '#^/api/auth/refresh$#',
            '#^/api/$#'
        ];
        foreach ($publicPatterns as $pattern) {
            if (preg_match($pattern, $uri)) {
                return true;
            }
        }
        return false;
    }

    private static function checkEndpointBoundary($uri, $platformType) {
        $allowedEndpoints = PLATFORM_ENDPOINTS[$platformType] ?? [];
        if (empty($allowedEndpoints)) {
            return false;
        }

        $uri = preg_replace('#^/api/#', '', $uri);
        $uriParts = explode('/', $uri);
        $module = $uriParts[0] ?? '';
        $subModule = $uriParts[1] ?? '';

        if ($module === 'auth') {
            return true;
        }

        if ($module === 'admin') {
            if ($platformType !== 'admin') {
                return false;
            }
            return $subModule === '' || in_array($subModule, $allowedEndpoints);
        }

        if ($module === 'client') {
            if ($platformType !== 'client') {
                return false;
            }
            return $subModule === '' || in_array($subModule, $allowedEndpoints);
        }

        return in_array($module, $allowedEndpoints) || empty($module);
    }

    public static function getCurrentPlatform() {
        return self::$currentPlatform;
    }

    public static function getPlatformInfo() {
        return [
            'name' => PLATFORM_NAME,
            'version' => PLATFORM_VERSION,
            'type' => PLATFORM_TYPE,
            'platforms' => self::PLATFORM_TYPES,
            'current' => self::$currentPlatform
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

    private static function getClientIp() {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}
