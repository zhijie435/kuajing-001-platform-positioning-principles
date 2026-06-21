<?php
require_once __DIR__ . '/../core/LicenseStore.php';

class CommercialGuard {
    const ERROR_LICENSE_INVALID = 4101;
    const ERROR_LICENSE_EXPIRED = 4102;
    const ERROR_USER_LIMIT_EXCEEDED = 4103;
    const ERROR_CLIENT_LIMIT_EXCEEDED = 4104;
    const ERROR_FEATURE_OUT_OF_BOUNDARY = 4105;
    const ERROR_TRIAL_EXPIRED = 4106;
    const ERROR_LICENSE_LOAD_FAILED = 4107;
    const ERROR_FEATURE_BOUNDARY_CONFIG_MISSING = 4108;
    const ERROR_EXPIRE_DATE_INVALID = 4109;

    private static $violations = [];
    private static $activeLicense = null;

    public static function validate() {
        $requestUri = self::getRequestUri();
        if (self::isPublicEndpoint($requestUri)) {
            return true;
        }

        self::validateLicense();
        self::validateExpiration();
        self::validateFeatureBoundary($requestUri);

        return true;
    }

    private static function getRequestUri() {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    private static function isPublicEndpoint($uri) {
        return (bool)preg_match('#^/api/auth/(login|platform-info|refresh)$#', $uri);
    }

    private static function ensureFeatureBoundaryReady() {
        return defined('COMMERCIAL_FEATURE_BOUNDARY') && is_array(COMMERCIAL_FEATURE_BOUNDARY);
    }

    public static function getActiveLicense() {
        if (self::$activeLicense !== null) {
            return self::$activeLicense;
        }

        try {
            $active = LicenseStore::getActive();
            if ($active) {
                self::$activeLicense = $active;
                return $active;
            }
        } catch (Exception $e) {
            self::recordViolation('license_store_exception', [
                'message' => $e->getMessage()
            ]);
        }

        if (!defined('LICENSE_KEY') || !defined('LICENSE_EXPIRE')) {
            self::recordViolation('license_constants_missing', []);
            throw new Exception(
                '系统 License 配置缺失，请联系管理员检查 config.php 中的 LICENSE_KEY / LICENSE_EXPIRE 定义',
                self::ERROR_LICENSE_LOAD_FAILED
            );
        }

        $key = LICENSE_KEY;
        $editionSuffix = substr($key, -3);
        $editionMap = defined('LicenseStore::EDITION_MAP') ? LicenseStore::EDITION_MAP : [];
        $editionInfo = $editionMap[$editionSuffix] ?? ($editionMap['STD'] ?? ['code' => 'standard', 'label' => '标准版']);

        $maxUsers = defined('LICENSE_MAX_USERS') ? LICENSE_MAX_USERS : 0;
        $maxClients = defined('LICENSE_MAX_CLIENTS') ? LICENSE_MAX_CLIENTS : 0;

        if (!self::ensureFeatureBoundaryReady()) {
            self::recordViolation('feature_boundary_config_missing', [
                'edition_code' => $editionInfo['code']
            ]);
            throw new Exception(
                '商用功能边界配置缺失，请联系管理员检查 COMMERCIAL_FEATURE_BOUNDARY 常量',
                self::ERROR_FEATURE_BOUNDARY_CONFIG_MISSING
            );
        }

        self::$activeLicense = [
            'license_key' => $key,
            'edition_code' => $editionInfo['code'],
            'edition_label' => $editionInfo['label'],
            'expire' => LICENSE_EXPIRE,
            'max_users' => $maxUsers,
            'max_clients' => $maxClients,
            'features' => self::getEditionFeatures($editionInfo['code'])
        ];

        self::recordViolation('license_fallback_to_constants', [
            'key' => substr($key, 0, 12) . '***',
            'edition' => $editionInfo['code']
        ]);

        return self::$activeLicense;
    }

    public static function refreshActiveLicense() {
        self::$activeLicense = null;
        return self::getActiveLicense();
    }

    private static function validateLicense() {
        $license = self::getActiveLicense();
        $licenseKey = $license['license_key'] ?? (defined('LICENSE_KEY') ? LICENSE_KEY : '');

        if (empty($licenseKey)) {
            self::recordViolation('license_key_empty', []);
            throw new Exception(
                'License Key 为空，系统未授权',
                self::ERROR_LICENSE_INVALID
            );
        }

        if (!LicenseStore::verifySignature($licenseKey)) {
            self::recordViolation('invalid_license', [
                'key' => substr($licenseKey, 0, 8) . '***',
                'edition_code' => $license['edition_code'] ?? null
            ]);
            throw new Exception(
                'License 签名校验失败，系统未授权',
                self::ERROR_LICENSE_INVALID
            );
        }
    }

    private static function validateExpiration() {
        $license = self::getActiveLicense();
        $expireStr = $license['expire'] ?? (defined('LICENSE_EXPIRE') ? LICENSE_EXPIRE : '');

        if (empty($expireStr)) {
            self::recordViolation('license_expire_empty', []);
            throw new Exception(
                'License 过期时间配置无效',
                self::ERROR_EXPIRE_DATE_INVALID
            );
        }

        $expireDate = strtotime($expireStr);
        if ($expireDate === false) {
            self::recordViolation('license_expire_invalid', [
                'expire_raw' => $expireStr
            ]);
            throw new Exception(
                sprintf('License 过期时间格式无效: %s', $expireStr),
                self::ERROR_EXPIRE_DATE_INVALID
            );
        }

        $today = time();

        if ($today > $expireDate) {
            self::recordViolation('license_expired', [
                'expired_at' => $expireStr,
                'days_overdue' => floor(($today - $expireDate) / 86400),
                'edition_code' => $license['edition_code'] ?? null
            ]);
            throw new Exception(
                sprintf('License 已过期 (%s)，请联系商务续费', $expireStr),
                self::ERROR_LICENSE_EXPIRED
            );
        }

        $daysLeft = floor(($expireDate - $today) / 86400);
        if ($daysLeft <= 30 && $daysLeft > 0) {
            header('X-License-Warning: expires-in-' . $daysLeft . '-days');
        }
    }

    public static function validateFeatureBoundary($requestUri) {
        $uri = preg_replace('#^/api/#', '', $requestUri);
        $uriParts = explode('/', $uri);
        $module = $uriParts[0] ?? '';
        $subModule = $uriParts[1] ?? '';
        $featureKey = $module . ($subModule ? '_' . $subModule : '');

        $bypassModules = ['auth', 'dashboard', 'admin'];
        if (in_array($module, $bypassModules, true)) {
            return true;
        }

        if (!self::ensureFeatureBoundaryReady()) {
            self::recordViolation('feature_boundary_config_missing_runtime', [
                'module' => $module,
                'feature' => $featureKey
            ]);
            throw new Exception(
                '商用功能边界配置缺失，无法校验功能权限',
                self::ERROR_FEATURE_BOUNDARY_CONFIG_MISSING
            );
        }

        $edition = self::getCurrentEdition();
        $allowedFeatures = self::getEditionFeatures($edition);

        if (empty($allowedFeatures)) {
            self::recordViolation('edition_features_empty', [
                'module' => $module,
                'feature' => $featureKey,
                'edition' => $edition
            ]);
            throw new Exception(
                sprintf('版本 [%s] 未配置功能边界，请联系商务开通相应功能', $edition),
                self::ERROR_FEATURE_OUT_OF_BOUNDARY
            );
        }

        $featureMatched = false;
        foreach ($allowedFeatures as $feature) {
            if (strpos($featureKey, $feature) === 0 || strpos($feature, $featureKey) === 0) {
                $featureMatched = true;
                break;
            }
        }

        if (!$featureMatched) {
            self::recordViolation('feature_out_of_boundary', [
                'module' => $module,
                'feature' => $featureKey,
                'edition' => $edition,
                'allowed' => $allowedFeatures
            ]);
            throw new Exception(
                sprintf(
                    '当前版本 [%s] 不支持该功能: %s，请升级版本',
                    $edition,
                    $featureKey
                ),
                self::ERROR_FEATURE_OUT_OF_BOUNDARY
            );
        }

        return true;
    }

    public static function validateUserQuota($currentUserCount) {
        $license = self::getActiveLicense();
        $maxUsers = $license['max_users'] ?? (defined('LICENSE_MAX_USERS') ? LICENSE_MAX_USERS : 0);

        if ($maxUsers <= 0) {
            self::recordViolation('user_quota_config_invalid', [
                'max_users' => $maxUsers
            ]);
            throw new Exception(
                'License 用户数上限配置无效，请联系管理员检查 License 配置',
                self::ERROR_USER_LIMIT_EXCEEDED
            );
        }

        if ($currentUserCount >= $maxUsers) {
            self::recordViolation('user_limit_exceeded', [
                'current' => $currentUserCount,
                'limit' => $maxUsers
            ]);
            throw new Exception(
                sprintf('用户数已达上限 (%d/%d)，请扩容', $currentUserCount, $maxUsers),
                self::ERROR_USER_LIMIT_EXCEEDED
            );
        }
        return true;
    }

    public static function validateClientQuota($currentClientCount) {
        $license = self::getActiveLicense();
        $maxClients = $license['max_clients'] ?? (defined('LICENSE_MAX_CLIENTS') ? LICENSE_MAX_CLIENTS : 0);

        if ($maxClients <= 0) {
            self::recordViolation('client_quota_config_invalid', [
                'max_clients' => $maxClients
            ]);
            throw new Exception(
                'License 客户数上限配置无效，请联系管理员检查 License 配置',
                self::ERROR_CLIENT_LIMIT_EXCEEDED
            );
        }

        if ($currentClientCount >= $maxClients) {
            self::recordViolation('client_limit_exceeded', [
                'current' => $currentClientCount,
                'limit' => $maxClients
            ]);
            throw new Exception(
                sprintf('客户数已达上限 (%d/%d)，请扩容', $currentClientCount, $maxClients),
                self::ERROR_CLIENT_LIMIT_EXCEEDED
            );
        }
        return true;
    }

    public static function getCurrentEdition() {
        $license = self::getActiveLicense();
        return $license['edition_code'] ?? 'standard';
    }

    private static function getEditionFeatures($edition) {
        if (!self::ensureFeatureBoundaryReady()) {
            return [];
        }
        $boundary = COMMERCIAL_FEATURE_BOUNDARY;
        $features = [];
        $priority = ['standard', 'professional', 'enterprise'];
        foreach ($priority as $ed) {
            if (!isset($boundary[$ed]) || !is_array($boundary[$ed])) continue;
            $features = array_merge($features, $boundary[$ed]);
            if ($ed === $edition) break;
        }
        return $features;
    }

    public static function getLicenseInfo() {
        $license = self::getActiveLicense();
        $expireStr = $license['expire'] ?? (defined('LICENSE_EXPIRE') ? LICENSE_EXPIRE : '');
        $expireDate = strtotime($expireStr);
        $daysLeft = $expireDate !== false ? max(0, floor(($expireDate - time()) / 86400)) : 0;

        $licenseKey = $license['license_key'] ?? (defined('LICENSE_KEY') ? LICENSE_KEY : '');

        return [
            'key' => substr($licenseKey, 0, 12) . '***',
            'full_key' => $licenseKey,
            'edition' => $license['edition_label'] ?? '标准版',
            'edition_code' => $license['edition_code'] ?? 'standard',
            'expire' => $expireStr,
            'issued_at' => $license['issued_at'] ?? null,
            'days_left' => $daysLeft,
            'max_users' => $license['max_users'] ?? (defined('LICENSE_MAX_USERS') ? LICENSE_MAX_USERS : 0),
            'max_clients' => $license['max_clients'] ?? (defined('LICENSE_MAX_CLIENTS') ? LICENSE_MAX_CLIENTS : 0),
            'features' => self::getEditionFeatures(self::getCurrentEdition()),
            'boundary' => self::ensureFeatureBoundaryReady() ? COMMERCIAL_FEATURE_BOUNDARY : [],
            'updated_at' => $license['updated_at'] ?? null
        ];
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
}
