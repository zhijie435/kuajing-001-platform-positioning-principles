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
    const ERROR_PLATFORM_MISSING = 4211;
    const ERROR_CONFIG_LOAD_FAILED = 4212;
    const ERROR_SESSION_START_FAILED = 4213;
    const ERROR_TOKEN_PAYLOAD_INVALID = 4214;

    private static $requestCache = [];
    private static $redLineEvents = [];
    private static $platformConfigCache = [];
    private static $allPlatformConfigCache = null;
    private static $configStorageFile = null;
    private static $sessionStarted = false;

    private static function initConfigStorage() {
        if (self::$configStorageFile === null) {
            self::$configStorageFile = dirname(__DIR__) . '/storage/redline_config.json';
        }
    }

    public static function loadPersistedConfigs() {
        self::initConfigStorage();
        if (!file_exists(self::$configStorageFile)) {
            return null;
        }
        $content = @file_get_contents(self::$configStorageFile);
        if (!$content) {
            self::triggerRedLine('config_read_empty', '红线持久化配置文件读取为空', [
                'file' => self::$configStorageFile
            ]);
            return null;
        }
        $data = json_decode($content, true);
        if (!is_array($data)) {
            self::triggerRedLine('config_json_invalid', '红线持久化配置 JSON 解析失败', [
                'file' => self::$configStorageFile,
                'raw_length' => strlen($content)
            ]);
            return null;
        }
        return $data;
    }

    public static function persistConfigs($configs) {
        self::initConfigStorage();
        $dir = dirname(self::$configStorageFile);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true)) {
                self::triggerRedLine('config_dir_create_failed', '红线配置存储目录创建失败', [
                    'dir' => $dir
                ]);
                return false;
            }
        }
        $tmp = self::$configStorageFile . '.tmp';
        $payload = [
            'configs' => $configs,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $writeResult = @file_put_contents($tmp, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($writeResult === false) {
            self::triggerRedLine('config_write_failed', '红线配置临时文件写入失败', [
                'tmp_file' => $tmp
            ]);
            return false;
        }
        if (!@rename($tmp, self::$configStorageFile)) {
            self::triggerRedLine('config_rename_failed', '红线配置文件替换失败', [
                'tmp_file' => $tmp,
                'target_file' => self::$configStorageFile
            ]);
            @unlink($tmp);
            return false;
        }
        self::$allPlatformConfigCache = null;
        self::$platformConfigCache = [];
        return true;
    }

    public static function clearConfigCache() {
        self::$platformConfigCache = [];
        self::$allPlatformConfigCache = null;
    }

    public static function validate() {
        $requestUri = self::getRequestUri();
        $isWhiteListed = self::isWhitelistedEndpoint($requestUri);

        $platform = PlatformGuard::getCurrentPlatform();
        if (!$platform && !$isWhiteListed) {
            self::triggerRedLine('platform_missing', '请求未携带有效的平台标识，无法进行红线校验', [
                'uri' => $requestUri
            ]);
            throw new Exception(
                '入口端标识缺失，无法完成红线安全校验，请重新从正确的入口登录',
                self::ERROR_PLATFORM_MISSING
            );
        }

        if ($platform) {
            $config = self::getPlatformConfig($platform);
            if (!$config['enabled']) {
                self::triggerRedLine('platform_disabled', '当前平台红线校验未启用', [
                    'platform' => $platform
                ]);
                throw new Exception(
                    '当前入口端红线校验未启用，访问被拒绝',
                    self::ERROR_PLATFORM_REDLINE_DISABLED
                );
            }
        }

        if (!$isWhiteListed) {
            self::validateIdentity();
        }

        if ($platform) {
            self::validateDeviceFingerprint();
            self::validateIpAddress();
            self::validateAccessHours();
            self::validateRateLimit();
        }

        if (!$isWhiteListed && $platform) {
            self::validateSession();
            self::validateMultiDeviceLogin();
        }

        return true;
    }

    public static function getPlatformConfig($platform = null) {
        if ($platform === null) {
            $platform = PlatformGuard::getCurrentPlatform();
        }
        if (!$platform) {
            return self::getDefaultConfig();
        }
        if (isset(self::$platformConfigCache[$platform])) {
            return self::$platformConfigCache[$platform];
        }

        $defaultConfig = self::getDefaultConfig();

        try {
            $platformConfigs = self::getMergedPlatformConfigs();
        } catch (Exception $e) {
            self::triggerRedLine('config_merge_failed', '红线配置合并失败，使用默认配置', [
                'platform' => $platform,
                'message' => $e->getMessage()
            ]);
            self::$platformConfigCache[$platform] = $defaultConfig;
            return $defaultConfig;
        }

        $platformConfig = isset($platformConfigs[$platform]) && is_array($platformConfigs[$platform])
            ? $platformConfigs[$platform]
            : [];

        self::$platformConfigCache[$platform] = array_merge($defaultConfig, $platformConfig);
        return self::$platformConfigCache[$platform];
    }

    private static function getDefaultConfig() {
        return [
            'enabled' => true,
            'ip_whitelist' => defined('RED_LINE_IP_WHITELIST') && is_array(RED_LINE_IP_WHITELIST) ? RED_LINE_IP_WHITELIST : [],
            'ip_whitelist_enforce' => false,
            'access_hours' => defined('RED_LINE_ACCESS_HOURS') && is_array(RED_LINE_ACCESS_HOURS)
                ? RED_LINE_ACCESS_HOURS
                : ['start' => '00:00', 'end' => '23:59'],
            'access_hours_enforce' => false,
            'max_requests_per_minute' => defined('RED_LINE_MAX_REQUESTS_PER_MINUTE') ? (int)RED_LINE_MAX_REQUESTS_PER_MINUTE : 300,
            'session_timeout' => defined('RED_LINE_SESSION_TIMEOUT') ? (int)RED_LINE_SESSION_TIMEOUT : 7200,
            'require_device_fingerprint' => false,
            'device_fingerprint_threshold' => 0.6,
            'allow_multi_device_login' => true,
            'sensitive_operation_2fa' => false
        ];
    }

    private static function getMergedPlatformConfigs() {
        $configs = defined('RED_LINE_PLATFORM_CONFIG') && is_array(RED_LINE_PLATFORM_CONFIG)
            ? RED_LINE_PLATFORM_CONFIG
            : [];
        $persisted = self::loadPersistedConfigs();
        if ($persisted && isset($persisted['configs']) && is_array($persisted['configs'])) {
            foreach ($persisted['configs'] as $p => $cfg) {
                if (!is_array($cfg)) continue;
                if (isset($configs[$p]) && is_array($configs[$p])) {
                    $configs[$p] = array_merge($configs[$p], $cfg);
                } else {
                    $configs[$p] = $cfg;
                }
            }
        }
        return $configs;
    }

    public static function getPlatformRedLineStatus() {
        $platform = PlatformGuard::getCurrentPlatform();
        $config = $platform ? self::getPlatformConfig($platform) : self::getDefaultConfig();
        $globalConfig = [
            'ip_whitelist' => defined('RED_LINE_IP_WHITELIST') && is_array(RED_LINE_IP_WHITELIST) ? RED_LINE_IP_WHITELIST : [],
            'access_hours' => defined('RED_LINE_ACCESS_HOURS') && is_array(RED_LINE_ACCESS_HOURS) ? RED_LINE_ACCESS_HOURS : ['start' => '00:00', 'end' => '23:59'],
            'rate_limit' => defined('RED_LINE_MAX_REQUESTS_PER_MINUTE') ? (int)RED_LINE_MAX_REQUESTS_PER_MINUTE : 300,
            'session_timeout' => defined('RED_LINE_SESSION_TIMEOUT') ? (int)RED_LINE_SESSION_TIMEOUT : 7200
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
        if (self::$allPlatformConfigCache !== null) {
            return self::$allPlatformConfigCache;
        }

        $result = [];
        $platforms = PlatformGuard::PLATFORM_TYPES;

        try {
            $mergedConfigs = self::getMergedPlatformConfigs();
        } catch (Exception $e) {
            self::triggerRedLine('all_config_merge_failed', '全平台红线配置合并失败', [
                'message' => $e->getMessage()
            ]);
            $mergedConfigs = [];
        }

        foreach ($platforms as $p) {
            $defaultConfig = self::getDefaultConfig();
            $platformConfig = isset($mergedConfigs[$p]) && is_array($mergedConfigs[$p])
                ? $mergedConfigs[$p]
                : [];
            $result[$p] = array_merge($defaultConfig, $platformConfig);
        }

        self::$allPlatformConfigCache = $result;
        return $result;
    }

    private static function isWhitelistedEndpoint($uri) {
        $whitelist = [
            '/api/auth/login',
            '/api/auth/platform-info',
            '/api/auth/refresh'
        ];
        return in_array($uri, $whitelist, true);
    }

    private static function getRequestUri() {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    private static function validateIdentity() {
        $token = self::getBearerToken();
        if (!$token) {
            self::triggerRedLine('identity_missing', '未提供身份凭证 Token');
            throw new Exception(
                '未登录或身份凭证缺失，请重新登录',
                self::ERROR_IDENTITY_INVALID
            );
        }

        $payload = self::verifyToken($token);
        if ($payload === false || !is_array($payload)) {
            self::triggerRedLine('token_invalid', 'Token 签名校验失败或 payload 格式异常', [
                'token_prefix' => substr($token, 0, 16)
            ]);
            throw new Exception(
                '身份凭证已失效或被篡改，请重新登录',
                self::ERROR_TOKEN_FORGED
            );
        }

        if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
            self::triggerRedLine('token_payload_invalid', 'Token payload 缺少 exp 字段或格式异常', [
                'payload_keys' => array_keys($payload)
            ]);
            throw new Exception(
                '身份凭证格式异常，请重新登录',
                self::ERROR_TOKEN_PAYLOAD_INVALID
            );
        }

        if ((int)$payload['exp'] < time()) {
            self::triggerRedLine('token_expired', 'Token 已过期', [
                'user_id' => $payload['user_id'] ?? null,
                'exp' => $payload['exp']
            ]);
            throw new Exception(
                '登录状态已过期，请重新登录',
                self::ERROR_SESSION_EXPIRED
            );
        }

        $platform = PlatformGuard::getCurrentPlatform();
        if ($platform && isset($payload['platform']) && $payload['platform'] !== $platform) {
            self::triggerRedLine('platform_mismatch', 'Token 平台类型不匹配', [
                'token_platform' => $payload['platform'],
                'request_platform' => $platform
            ]);
            throw new Exception(
                sprintf(
                    '身份凭证与入口端不匹配 (token: %s, 当前: %s)',
                    $payload['platform'],
                    $platform
                ),
                self::ERROR_IDENTITY_INVALID
            );
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
            throw new Exception(
                '当前入口端要求设备指纹校验，请启用设备指纹',
                self::ERROR_DEVICE_FINGERPRINT_REQUIRED
            );
        }

        if (!$headerFp) {
            return true;
        }

        $serverFp = self::calculateServerFingerprint();
        $similarity = self::fingerprintSimilarity($headerFp, $serverFp);
        $threshold = isset($config['device_fingerprint_threshold']) ? (float)$config['device_fingerprint_threshold'] : 0.6;

        if ($similarity < $threshold) {
            self::triggerRedLine('device_mismatch', '设备指纹不匹配', [
                'header_fp' => substr($headerFp, 0, 16),
                'server_fp' => substr($serverFp, 0, 16),
                'similarity' => $similarity,
                'threshold' => $threshold,
                'platform' => PlatformGuard::getCurrentPlatform()
            ]);
            throw new Exception(
                '设备环境异常，疑似凭证被劫持',
                self::ERROR_DEVICE_FINGERPRINT_MISMATCH
            );
        }

        self::$requestCache['device_fp'] = $headerFp;
        return true;
    }

    private static function validateIpAddress() {
        $config = self::getPlatformConfig();
        $clientIp = self::getClientIp();
        $whitelist = isset($config['ip_whitelist']) && is_array($config['ip_whitelist']) ? $config['ip_whitelist'] : [];

        if (empty($config['ip_whitelist_enforce'])) {
            self::$requestCache['client_ip'] = $clientIp;
            return true;
        }

        if (empty($whitelist)) {
            self::triggerRedLine('ip_whitelist_empty', 'IP 白名单强制启用但规则为空', [
                'platform' => PlatformGuard::getCurrentPlatform()
            ]);
            self::$requestCache['client_ip'] = $clientIp;
            return true;
        }

        $allowed = false;
        foreach ($whitelist as $range) {
            if (!is_string($range)) continue;
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
            throw new Exception(
                sprintf('%s端访问受限，IP %s 未授权', $platform, $clientIp),
                self::ERROR_IP_BLOCKED
            );
        }

        self::$requestCache['client_ip'] = $clientIp;
        return true;
    }

    private static function validateAccessHours() {
        $config = self::getPlatformConfig();

        if (empty($config['access_hours_enforce'])) {
            return true;
        }

        $hours = isset($config['access_hours']) && is_array($config['access_hours'])
            ? $config['access_hours']
            : ['start' => '00:00', 'end' => '23:59'];

        $now = date('H:i');
        $start = $hours['start'] ?? '00:00';
        $end = $hours['end'] ?? '23:59';

        if ($start === '00:00' && $end === '23:59') {
            return true;
        }

        $startTime = strtotime($start);
        $endTime = strtotime($end);
        $nowTime = strtotime($now);

        if ($startTime === false || $endTime === false || $nowTime === false) {
            self::triggerRedLine('access_hours_invalid', '访问时段配置格式无效', [
                'start' => $start,
                'end' => $end,
                'platform' => PlatformGuard::getCurrentPlatform()
            ]);
            return true;
        }

        if ($nowTime < $startTime || $nowTime > $endTime) {
            self::triggerRedLine('outside_hours', '非工作时段访问', [
                'time' => $now,
                'allowed' => $hours,
                'platform' => PlatformGuard::getCurrentPlatform()
            ]);
            throw new Exception(
                sprintf(
                    '%s端非允许访问时段 (%s - %s)，当前 %s',
                    PlatformGuard::getCurrentPlatform(),
                    $start,
                    $end,
                    $now
                ),
                self::ERROR_ACCESS_OUTSIDE_HOURS
            );
        }

        return true;
    }

    private static function validateRateLimit() {
        $config = self::getPlatformConfig();
        $clientIp = self::getClientIp();
        $platform = PlatformGuard::getCurrentPlatform();
        $limit = isset($config['max_requests_per_minute']) ? (int)$config['max_requests_per_minute'] : 300;

        if ($limit <= 0) {
            self::triggerRedLine('rate_limit_config_invalid', '限流配置无效（<=0），跳过快照', [
                'limit' => $limit,
                'platform' => $platform
            ]);
            return true;
        }

        $identifier = 'rate_' . $platform . '_' . $clientIp . '_' . date('YmdHi');
        $count = self::getRateCount($identifier);

        if ($count >= $limit) {
            self::triggerRedLine('rate_limit', '请求频率超限', [
                'ip' => $clientIp,
                'count' => $count,
                'limit' => $limit,
                'platform' => $platform
            ]);
            throw new Exception(
                sprintf('%s端请求过于频繁 (%d/%d次/分钟)，请稍后重试', $platform, $count, $limit),
                self::ERROR_RATE_LIMIT_EXCEEDED
            );
        }

        self::incrementRateCount($identifier);
        return true;
    }

    private static function validateSession() {
        $config = self::getPlatformConfig();
        $token = self::getBearerToken();
        if (!$token) {
            self::triggerRedLine('session_token_missing', '会话校验时 Token 已丢失', []);
            return false;
        }

        $payload = self::$requestCache['user'] ?? self::verifyToken($token);
        if (!$payload || !is_array($payload)) {
            self::triggerRedLine('session_payload_missing', '会话校验时 payload 已丢失或无效', []);
            return false;
        }

        $sessionId = $payload['session_id'] ?? 'default';
        $sessionKey = 'session_' . $sessionId;
        $lastActive = self::getSessionLastActive($sessionKey);

        $timeout = isset($config['session_timeout']) ? (int)$config['session_timeout'] : 7200;
        if ($timeout <= 0) {
            self::triggerRedLine('session_timeout_config_invalid', '会话超时配置无效（<=0），跳过会话超时校验', [
                'timeout' => $timeout
            ]);
            return true;
        }

        if ($lastActive && (time() - $lastActive) > $timeout) {
            self::triggerRedLine('session_timeout', '会话超时', [
                'user_id' => $payload['user_id'] ?? null,
                'idle_seconds' => time() - $lastActive,
                'timeout_seconds' => $timeout,
                'platform' => PlatformGuard::getCurrentPlatform()
            ]);
            throw new Exception(
                sprintf('会话超时，长时间未操作（%d秒），请重新登录', $timeout),
                self::ERROR_SESSION_EXPIRED
            );
        }

        self::updateSessionLastActive($sessionKey);
        return true;
    }

    private static function validateMultiDeviceLogin() {
        $config = self::getPlatformConfig();

        if (!empty($config['allow_multi_device_login'])) {
            return true;
        }

        $user = self::getCurrentUser();
        if (!$user || !is_array($user)) {
            self::triggerRedLine('multi_device_user_missing', '多设备登录校验时用户信息缺失', []);
            return true;
        }

        $headerFp = $_SERVER['HTTP_X_DEVICE_FINGERPRINT'] ?? null;
        if (!$headerFp) {
            self::triggerRedLine('multi_device_fp_missing', '多设备登录校验时设备指纹缺失，无法判定是否为新设备', []);
            return true;
        }

        $userId = $user['user_id'] ?? 0;
        $platform = PlatformGuard::getCurrentPlatform();
        $deviceKey = 'device_fp_' . $platform . '_' . $userId;

        $registeredFp = self::getRegisteredDeviceFingerprint($deviceKey);

        if (!$registeredFp) {
            self::registerDeviceFingerprint($deviceKey, $headerFp);
            self::triggerRedLine('multi_device_first_register', '首次注册设备指纹', [
                'user_id' => $userId,
                'platform' => $platform,
                'fp_prefix' => substr($headerFp, 0, 16)
            ]);
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
        if (!defined('JWT_SECRET')) {
            self::triggerRedLine('jwt_secret_missing', 'JWT_SECRET 常量未定义，无法生成 Token', []);
            throw new Exception(
                '系统身份认证配置缺失，请联系管理员检查 JWT_SECRET 配置',
                self::ERROR_CONFIG_LOAD_FAILED
            );
        }
        $expire = defined('JWT_EXPIRE') ? (int)JWT_EXPIRE : 7200;
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'user_id' => $userId,
            'username' => $userName,
            'role' => $role,
            'platform' => $platform,
            'iat' => time(),
            'exp' => time() + $expire,
            'session_id' => md5($userId . microtime(true))
        ]));
        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
        return "$header.$payload.$signature";
    }

    public static function verifyToken($token) {
        if (!defined('JWT_SECRET')) {
            self::triggerRedLine('jwt_secret_missing_verify', 'JWT_SECRET 常量未定义，无法校验 Token', []);
            return false;
        }
        if (!is_string($token)) {
            return false;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;
        $expected = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

        if (hash_equals($expected, $signature)) {
            $decoded = base64_decode($payload, true);
            if ($decoded === false) {
                return false;
            }
            $data = json_decode($decoded, true);
            return is_array($data) ? $data : false;
        }
        return false;
    }

    private static function getBearerToken() {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!is_string($auth)) {
            return null;
        }
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
        if (!is_string($fp1) || !is_string($fp2) || $fp1 === '' || $fp2 === '') {
            return 0;
        }
        $distance = levenshtein($fp1, $fp2);
        $maxLen = max(strlen($fp1), strlen($fp2));
        return $maxLen > 0 ? 1 - ($distance / $maxLen) : 0;
    }

    private static function ipInRange($ip, $range) {
        if (!is_string($ip) || !is_string($range)) {
            return false;
        }
        if (strpos($range, '/') === false) {
            return hash_equals($ip, $range);
        }
        [$subnet, $mask] = explode('/', $range, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $maskInt = (int)$mask;
        if ($maskInt < 0 || $maskInt > 32) {
            return false;
        }
        $maskLong = -1 << (32 - $maskInt);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    private static function getClientIp() {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    private static function ensureSessionStarted() {
        if (self::$sessionStarted) {
            return true;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$sessionStarted = true;
            return true;
        }
        if (session_status() === PHP_SESSION_NONE) {
            $started = @session_start();
            if (!$started) {
                self::triggerRedLine('session_start_failed', 'Session 启动失败，基于 Session 的校验将失效', []);
                return false;
            }
            self::$sessionStarted = true;
            return true;
        }
        return false;
    }

    private static function getRateCount($key) {
        if (!self::ensureSessionStarted()) {
            return 0;
        }
        return isset($_SESSION[$key]) ? (int)$_SESSION[$key] : 0;
    }

    private static function incrementRateCount($key) {
        if (!self::ensureSessionStarted()) {
            return;
        }
        $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
    }

    private static function getSessionLastActive($key) {
        if (!self::ensureSessionStarted()) {
            return null;
        }
        $lastActive = $_SESSION[$key . '_last_active'] ?? null;
        return $lastActive !== null ? (int)$lastActive : null;
    }

    private static function updateSessionLastActive($key) {
        if (!self::ensureSessionStarted()) {
            return;
        }
        $_SESSION[$key . '_last_active'] = time();
    }

    private static function getRegisteredDeviceFingerprint($key) {
        if (!self::ensureSessionStarted()) {
            return null;
        }
        return $_SESSION[$key] ?? null;
    }

    private static function registerDeviceFingerprint($key, $fingerprint) {
        if (!self::ensureSessionStarted()) {
            return;
        }
        $_SESSION[$key] = $fingerprint;
    }

    private static function triggerRedLine($type, $message, $data = []) {
        self::$redLineEvents[] = [
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'time' => date('Y-m-d H:i:s'),
            'uri' => self::getRequestUri(),
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
