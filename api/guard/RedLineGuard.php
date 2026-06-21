<?php
class RedLineGuard {
    const ERROR_IDENTITY_INVALID = 4201;
    const ERROR_DEVICE_FINGERPRINT_MISMATCH = 4202;
    const ERROR_IP_BLOCKED = 4203;
    const ERROR_ACCESS_OUTSIDE_HOURS = 4204;
    const ERROR_RATE_LIMIT_EXCEEDED = 4205;
    const ERROR_SESSION_EXPIRED = 4206;
    const ERROR_TOKEN_FORGED = 4207;
    const ERROR_MULTI_DEVICE_LOGIN = 4208;
    const ERROR_DEVICE_FINGERPRINT_REQUIRED = 4209;
    const ERROR_PLATFORM_REDLINE_DISABLED = 4210;

    private static $requestCache = [];
    private static $redLineEvents = [];
    private static $platformConfig = null;

    public static function validate() {
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $isWhiteListed = self::isWhitelistedEndpoint($requestUri);

        $platform = PlatformGuard::getCurrentPlatform();
        $config = self::getPlatformConfig($platform);

        if (!$config['enabled']) {
            self::triggerRedLine('platform_disabled', '当前平台红线校验未启用', [
                'platform' => $platform
            ]);
            throw new Exception('当前入口端红线校验未启用，访问被拒绝', self::ERROR_PLATFORM_REDLINE_DISABLED);
        }

        if (!$isWhiteListed) {
            self::validateIdentity();
        }

        self::validateDeviceFingerprint();
        self::validateIpAddress();
        self::validateAccessHours();
        self::validateRateLimit();

        if (!$isWhiteListed) {
            self::validateSession();
            self::validateMultiDeviceLogin();
        }

        return true;
    }

    public static function getPlatformConfig($platform = null) {
        if ($platform === null) {
            $platform = PlatformGuard::getCurrentPlatform();
        }
        if (self::$platformConfig !== null) {
            return self::$platformConfig;
        }

        $defaultConfig = [
            'enabled' => true,
            'ip_whitelist' => RED_LINE_IP_WHITELIST,
            'ip_whitelist_enforce' => false,
            'access_hours' => RED_LINE_ACCESS_HOURS,
            'access_hours_enforce' => false,
            'max_requests_per_minute' => RED_LINE_MAX_REQUESTS_PER_MINUTE,
            'session_timeout' => RED_LINE_SESSION_TIMEOUT,
            'require_device_fingerprint' => false,
            'device_fingerprint_threshold' => 0.6,
            'allow_multi_device_login' => true,
            'sensitive_operation_2fa' => false
        ];

        $platformConfigs = RED_LINE_PLATFORM_CONFIG;
        $platformConfig = $platformConfigs[$platform] ?? [];

        self::$platformConfig = array_merge($defaultConfig, $platformConfig);
        return self::$platformConfig;
    }

    public static function getPlatformRedLineStatus() {
        $platform = PlatformGuard::getCurrentPlatform();
        $config = self::getPlatformConfig($platform);
        $globalConfig = [
            'ip_whitelist' => RED_LINE_IP_WHITELIST,
            'access_hours' => RED_LINE_ACCESS_HOURS,
            'rate_limit' => RED_LINE_MAX_REQUESTS_PER_MINUTE,
            'session_timeout' => RED_LINE_SESSION_TIMEOUT
        ];

        return [
            'platform' => $platform,
            'enabled' => $config['enabled'],
            'platform_config' => $config,
            'global_default' => $globalConfig,
            'events' => self::$redLineEvents,
            'events_count' => count(self::$redLineEvents)
        ];
    }

    public static function getAllPlatformRedLineStatus() {
        $result = [];
        $platforms = PlatformGuard::PLATFORM_TYPES;
        foreach ($platforms as $p) {
            $defaultConfig = [
                'enabled' => true,
                'ip_whitelist' => RED_LINE_IP_WHITELIST,
                'ip_whitelist_enforce' => false,
                'access_hours' => RED_LINE_ACCESS_HOURS,
                'access_hours_enforce' => false,
                'max_requests_per_minute' => RED_LINE_MAX_REQUESTS_PER_MINUTE,
                'session_timeout' => RED_LINE_SESSION_TIMEOUT,
                'require_device_fingerprint' => false,
                'device_fingerprint_threshold' => 0.6,
                'allow_multi_device_login' => true,
                'sensitive_operation_2fa' => false
            ];
            $platformConfigs = RED_LINE_PLATFORM_CONFIG;
            $platformConfig = $platformConfigs[$p] ?? [];
            $result[$p] = array_merge($defaultConfig, $platformConfig);
        }
        return $result;
    }

    private static function isWhitelistedEndpoint($uri) {
        $whitelist = [
            '/api/auth/login',
            '/api/auth/platform-info',
            '/api/auth/refresh'
        ];
        return in_array($uri, $whitelist);
    }

    private static function validateIdentity() {
        $token = self::getBearerToken();
        if (!$token) {
            self::triggerRedLine('identity_missing', '未提供身份凭证 Token');
            throw new Exception('未登录或身份凭证缺失，请重新登录', self::ERROR_IDENTITY_INVALID);
        }

        $payload = self::verifyToken($token);
        if (!$payload) {
            self::triggerRedLine('token_invalid', 'Token 签名校验失败', ['token_prefix' => substr($token, 0, 16)]);
            throw new Exception('身份凭证已失效或被篡改，请重新登录', self::ERROR_TOKEN_FORGED);
        }

        if ($payload['exp'] < time()) {
            self::triggerRedLine('token_expired', 'Token 已过期', ['user_id' => $payload['user_id'] ?? null]);
            throw new Exception('登录状态已过期，请重新登录', self::ERROR_SESSION_EXPIRED);
        }

        $platform = PlatformGuard::getCurrentPlatform();
        if ($platform && isset($payload['platform']) && $payload['platform'] !== $platform) {
            self::triggerRedLine('platform_mismatch', 'Token 平台类型不匹配', [
                'token_platform' => $payload['platform'],
                'request_platform' => $platform
            ]);
            throw new Exception(sprintf(
                '身份凭证与入口端不匹配 (token: %s, 当前: %s)',
                $payload['platform'],
                $platform
            ), self::ERROR_IDENTITY_INVALID);
        }

        self::$requestCache['user'] = $payload;
        return true;
    }

    private static function validateDeviceFingerprint() {
        $config = self::getPlatformConfig();
        $headerFp = $_SERVER['HTTP_X_DEVICE_FINGERPRINT'] ?? null;

        if ($config['require_device_fingerprint'] && !$headerFp) {
            self::triggerRedLine('device_fingerprint_missing', '设备指纹缺失', [
                'platform' => PlatformGuard::getCurrentPlatform()
            ]);
            throw new Exception('当前入口端要求设备指纹校验，请启用设备指纹', self::ERROR_DEVICE_FINGERPRINT_REQUIRED);
        }

        if (!$headerFp) {
            return true;
        }

        $serverFp = self::calculateServerFingerprint();
        $similarity = self::fingerprintSimilarity($headerFp, $serverFp);
        $threshold = $config['device_fingerprint_threshold'];

        if ($similarity < $threshold) {
            self::triggerRedLine('device_mismatch', '设备指纹不匹配', [
                'header_fp' => substr($headerFp, 0, 16),
                'server_fp' => substr($serverFp, 0, 16),
                'similarity' => $similarity,
                'threshold' => $threshold,
                'platform' => PlatformGuard::getCurrentPlatform()
            ]);
            throw new Exception('设备环境异常，疑似凭证被劫持', self::ERROR_DEVICE_FINGERPRINT_MISMATCH);
        }

        self::$requestCache['device_fp'] = $headerFp;
        return true;
    }

    private static function validateIpAddress() {
        $config = self::getPlatformConfig();
        $clientIp = self::getClientIp();
        $whitelist = $config['ip_whitelist'];

        if (!$config['ip_whitelist_enforce']) {
            self::$requestCache['client_ip'] = $clientIp;
            return true;
        }

        if (empty($whitelist)) {
            self::$requestCache['client_ip'] = $clientIp;
            return true;
        }

        $allowed = false;
        foreach ($whitelist as $range) {
            if (self::ipInRange($clientIp, $range)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            $platform = PlatformGuard::getCurrentPlatform();
            self::triggerRedLine('ip_blocked', 'IP 不在白名单内', [
                'ip' => $clientIp,
                'platform' => $platform
            ]);
            throw new Exception(sprintf(
                '%s端访问受限，IP %s 未授权',
                $platform,
                $clientIp
            ), self::ERROR_IP_BLOCKED);
        }

        self::$requestCache['client_ip'] = $clientIp;
        return true;
    }

    private static function validateAccessHours() {
        $config = self::getPlatformConfig();

        if (!$config['access_hours_enforce']) {
            return true;
        }

        $hours = $config['access_hours'];
        $now = date('H:i');
        $start = $hours['start'];
        $end = $hours['end'];

        if ($start === '00:00' && $end === '23:59') {
            return true;
        }

        if ($now < $start || $now > $end) {
            self::triggerRedLine('outside_hours', '非工作时段访问', [
                'time' => $now,
                'allowed' => $hours,
                'platform' => PlatformGuard::getCurrentPlatform()
            ]);
            throw new Exception(sprintf(
                '%s端非允许访问时段 (%s - %s)，当前 %s',
                PlatformGuard::getCurrentPlatform(),
                $start,
                $end,
                $now
            ), self::ERROR_ACCESS_OUTSIDE_HOURS);
        }

        return true;
    }

    private static function validateRateLimit() {
        $config = self::getPlatformConfig();
        $clientIp = self::getClientIp();
        $platform = PlatformGuard::getCurrentPlatform();
        $identifier = 'rate_' . $platform . '_' . $clientIp . '_' . date('YmdHi');

        $count = self::getRateCount($identifier);
        $limit = $config['max_requests_per_minute'];

        if ($count >= $limit) {
            self::triggerRedLine('rate_limit', '请求频率超限', [
                'ip' => $clientIp,
                'count' => $count,
                'limit' => $limit,
                'platform' => $platform
            ]);
            throw new Exception(sprintf(
                '%s端请求过于频繁 (%d/%d次/分钟)，请稍后重试',
                $platform,
                $count,
                $limit
            ), self::ERROR_RATE_LIMIT_EXCEEDED);
        }

        self::incrementRateCount($identifier);
        return true;
    }

    private static function validateSession() {
        $config = self::getPlatformConfig();
        $token = self::getBearerToken();
        if (!$token) return false;

        $payload = self::verifyToken($token);
        if (!$payload) return false;

        $sessionKey = 'session_' . ($payload['session_id'] ?? 'default');
        $lastActive = self::getSessionLastActive($sessionKey);

        $timeout = $config['session_timeout'];
        if ($lastActive && (time() - $lastActive) > $timeout) {
            self::triggerRedLine('session_timeout', '会话超时', [
                'user_id' => $payload['user_id'] ?? null,
                'idle_seconds' => time() - $lastActive,
                'timeout_seconds' => $timeout,
                'platform' => PlatformGuard::getCurrentPlatform()
            ]);
            throw new Exception(sprintf(
                '会话超时，长时间未操作（%d秒），请重新登录',
                $timeout
            ), self::ERROR_SESSION_EXPIRED);
        }

        self::updateSessionLastActive($sessionKey);
        return true;
    }

    private static function validateMultiDeviceLogin() {
        $config = self::getPlatformConfig();

        if ($config['allow_multi_device_login']) {
            return true;
        }

        $user = self::getCurrentUser();
        if (!$user) {
            return true;
        }

        $headerFp = $_SERVER['HTTP_X_DEVICE_FINGERPRINT'] ?? null;
        if (!$headerFp) {
            return true;
        }

        $userId = $user['user_id'] ?? 0;
        $platform = PlatformGuard::getCurrentPlatform();
        $deviceKey = 'device_fp_' . $platform . '_' . $userId;

        $registeredFp = self::getRegisteredDeviceFingerprint($deviceKey);

        if (!$registeredFp) {
            self::registerDeviceFingerprint($deviceKey, $headerFp);
            return true;
        }

        if ($registeredFp !== $headerFp) {
            self::triggerRedLine('multi_device_login', '多设备登录被拒绝', [
                'user_id' => $userId,
                'platform' => $platform,
                'registered_fp' => substr($registeredFp, 0, 16),
                'current_fp' => substr($headerFp, 0, 16)
            ]);
            throw new Exception(
                sprintf('%s端不允许多设备同时登录，请先在其他设备退出', $platform),
                self::ERROR_MULTI_DEVICE_LOGIN
            );
        }

        return true;
    }

    public static function getCurrentUser() {
        return self::$requestCache['user'] ?? null;
    }

    public static function generateToken($userId, $userName, $role, $platform) {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'user_id' => $userId,
            'username' => $userName,
            'role' => $role,
            'platform' => $platform,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRE,
            'session_id' => md5($userId . microtime(true))
        ]));
        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
        return "$header.$payload.$signature";
    }

    public static function verifyToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;

        [$header, $payload, $signature] = $parts;
        $expected = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

        if (hash_equals($expected, $signature)) {
            return json_decode(base64_decode($payload), true);
        }
        return false;
    }

    private static function getBearerToken() {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private static function calculateServerFingerprint() {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            self::getClientIp()
        ];
        return md5(implode('|', $components));
    }

    private static function fingerprintSimilarity($fp1, $fp2) {
        $distance = levenshtein($fp1, $fp2);
        $maxLen = max(strlen($fp1), strlen($fp2));
        return $maxLen > 0 ? 1 - ($distance / $maxLen) : 0;
    }

    private static function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        [$subnet, $mask] = explode('/', $range);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int)$mask);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    private static function getClientIp() {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    private static function getRateCount($key) {
        return $_SESSION[$key] ?? 0;
    }

    private static function incrementRateCount($key) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
    }

    private static function getSessionLastActive($key) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        return $_SESSION[$key . '_last_active'] ?? null;
    }

    private static function updateSessionLastActive($key) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION[$key . '_last_active'] = time();
    }

    private static function getRegisteredDeviceFingerprint($key) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        return $_SESSION[$key] ?? null;
    }

    private static function registerDeviceFingerprint($key, $fingerprint) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION[$key] = $fingerprint;
    }

    private static function triggerRedLine($type, $message, $data = []) {
        self::$redLineEvents[] = [
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'time' => date('Y-m-d H:i:s'),
            'uri' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
            'ip' => self::getClientIp(),
            'platform' => PlatformGuard::getCurrentPlatform()
        ];
    }

    public static function getRedLineEvents() {
        return self::$redLineEvents;
    }

    public static function getStatus() {
        return [
            'pass' => true,
            'user' => self::getCurrentUser(),
            'ip' => self::getClientIp(),
            'events_count' => count(self::$redLineEvents),
            'events' => self::$redLineEvents
        ];
    }
}
