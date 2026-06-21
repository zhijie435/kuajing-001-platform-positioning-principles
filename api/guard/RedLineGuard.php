<?php
class RedLineGuard {
    const ERROR_IDENTITY_INVALID = 4201;
    const ERROR_DEVICE_FINGERPRINT_MISMATCH = 4202;
    const ERROR_IP_BLOCKED = 4203;
    const ERROR_ACCESS_OUTSIDE_HOURS = 4204;
    const ERROR_RATE_LIMIT_EXCEEDED = 4205;
    const ERROR_SESSION_EXPIRED = 4206;
    const ERROR_TOKEN_FORGED = 4207;

    private static $requestCache = [];
    private static $redLineEvents = [];

    public static function validate() {
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $isWhiteListed = self::isWhitelistedEndpoint($requestUri);

        if (!$isWhiteListed) {
            self::validateIdentity();
        }

        self::validateDeviceFingerprint();
        self::validateIpAddress();
        self::validateAccessHours();
        self::validateRateLimit();

        if (!$isWhiteListed) {
            self::validateSession();
        }

        return true;
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
        $headerFp = $_SERVER['HTTP_X_DEVICE_FINGERPRINT'] ?? null;
        if (!$headerFp) {
            return true;
        }

        $serverFp = self::calculateServerFingerprint();
        $similarity = self::fingerprintSimilarity($headerFp, $serverFp);

        if ($similarity < 0.6) {
            self::triggerRedLine('device_mismatch', '设备指纹不匹配', [
                'header_fp' => substr($headerFp, 0, 16),
                'server_fp' => substr($serverFp, 0, 16),
                'similarity' => $similarity
            ]);
            throw new Exception('设备环境异常，疑似凭证被劫持', self::ERROR_DEVICE_FINGERPRINT_MISMATCH);
        }

        self::$requestCache['device_fp'] = $headerFp;
        return true;
    }

    private static function validateIpAddress() {
        $clientIp = self::getClientIp();
        $whitelist = RED_LINE_IP_WHITELIST;

        if (empty($whitelist)) {
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
            if ($platform === 'admin') {
                self::triggerRedLine('ip_blocked', '管理端 IP 不在白名单内', [
                    'ip' => $clientIp,
                    'platform' => $platform
                ]);
                throw new Exception(sprintf(
                    '管理端访问受限，IP %s 未授权',
                    $clientIp
                ), self::ERROR_IP_BLOCKED);
            }
        }

        self::$requestCache['client_ip'] = $clientIp;
        return true;
    }

    private static function validateAccessHours() {
        $hours = RED_LINE_ACCESS_HOURS;
        $now = date('H:i');
        $start = $hours['start'];
        $end = $hours['end'];

        if ($start === '00:00' && $end === '23:59') {
            return true;
        }

        if ($now < $start || $now > $end) {
            self::triggerRedLine('outside_hours', '非工作时段访问', [
                'time' => $now,
                'allowed' => $hours
            ]);
            throw new Exception(sprintf(
                '非允许访问时段 (%s - %s)，当前 %s',
                $start, $end, $now
            ), self::ERROR_ACCESS_OUTSIDE_HOURS);
        }

        return true;
    }

    private static function validateRateLimit() {
        $clientIp = self::getClientIp();
        $identifier = 'rate_' . $clientIp . '_' . date('YmdHi');

        $count = self::getRateCount($identifier);
        $limit = RED_LINE_MAX_REQUESTS_PER_MINUTE;

        if ($count >= $limit) {
            self::triggerRedLine('rate_limit', '请求频率超限', [
                'ip' => $clientIp,
                'count' => $count,
                'limit' => $limit
            ]);
            throw new Exception(sprintf(
                '请求过于频繁 (%d/%d次/分钟)，请稍后重试',
                $count,
                $limit
            ), self::ERROR_RATE_LIMIT_EXCEEDED);
        }

        self::incrementRateCount($identifier);
        return true;
    }

    private static function validateSession() {
        $token = self::getBearerToken();
        if (!$token) return false;

        $payload = self::verifyToken($token);
        if (!$payload) return false;

        $sessionKey = 'session_' . ($payload['session_id'] ?? 'default');
        $lastActive = self::getSessionLastActive($sessionKey);

        if ($lastActive && (time() - $lastActive) > RED_LINE_SESSION_TIMEOUT) {
            self::triggerRedLine('session_timeout', '会话超时', [
                'user_id' => $payload['user_id'] ?? null,
                'idle_seconds' => time() - $lastActive
            ]);
            throw new Exception('会话超时，长时间未操作，请重新登录', self::ERROR_SESSION_EXPIRED);
        }

        self::updateSessionLastActive($sessionKey);
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

    private static function triggerRedLine($type, $message, $data = []) {
        self::$redLineEvents[] = [
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'time' => date('Y-m-d H:i:s'),
            'uri' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
            'ip' => self::getClientIp()
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
