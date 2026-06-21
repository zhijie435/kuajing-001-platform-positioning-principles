<?php
require_once __DIR__ . '/../core/LicenseStore.php';

class CommercialGuard {
    const ERROR_LICENSE_INVALID = 4101;
    const ERROR_LICENSE_EXPIRED = 4102;
    const ERROR_USER_LIMIT_EXCEEDED = 4103;
    const ERROR_CLIENT_LIMIT_EXCEEDED = 4104;
    const ERROR_FEATURE_OUT_OF_BOUNDARY = 4105;
    const ERROR_TRIAL_EXPIRED = 4106;

    private static $violations = [];
    private static $activeLicense = null;

    public static function validate() {
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (self::isPublicEndpoint($requestUri)) {
            return true;
        }

        self::validateLicense();
        self::validateExpiration();
        self::validateFeatureBoundary($requestUri);

        return true;
    }

    private static function isPublicEndpoint($uri) {
        return (bool)preg_match('#^/api/auth/(login|platform-info|refresh)$#', $uri);
    }

    public static function getActiveLicense() {
        if (self::$activeLicense !== null) {
            return self::$activeLicense;
        }

        $active = LicenseStore::getActive();
        if ($active) {
            self::$activeLicense = $active;
            return $active;
        }

        $key = LICENSE_KEY;
        $editionSuffix = substr($key, -3);
        $editionInfo = LicenseStore::EDITION_MAP[$editionSuffix] ?? LicenseStore::EDITION_MAP['STD'];
        self::$activeLicense = [
            'license_key' => $key,
            'edition_code' => $editionInfo['code'],
            'edition_label' => $editionInfo['label'],
            'expire' => LICENSE_EXPIRE,
            'max_users' => LICENSE_MAX_USERS,
            'max_clients' => LICENSE_MAX_CLIENTS,
            'features' => self::getEditionFeatures($editionInfo['code'])
        ];
        return self::$activeLicense;
    }

    public static function refreshActiveLicense() {
        self::$activeLicense = null;
        return self::getActiveLicense();
    }

    private static function validateLicense() {
        $license = self::getActiveLicense();
        $licenseKey = $license['license_key'] ?? LICENSE_KEY;

        if (!LicenseStore::verifySignature($licenseKey)) {
            self::recordViolation('invalid_license', ['key' => substr($licenseKey, 0, 8) . '***']);
            throw new Exception('License 签名校验失败，系统未授权', self::ERROR_LICENSE_INVALID);
        }
    }

    private static function validateExpiration() {
        $license = self::getActiveLicense();
        $expireStr = $license['expire'] ?? LICENSE_EXPIRE;
        $expireDate = strtotime($expireStr);
        $today = time();

        if ($today > $expireDate) {
            self::recordViolation('license_expired', [
                'expired_at' => $expireStr,
                'days_overdue' => floor(($today - $expireDate) / 86400)
            ]);
            throw new Exception(sprintf(
                'License 已过期 (%s)，请联系商务续费',
                $expireStr
            ), self::ERROR_LICENSE_EXPIRED);
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

        if ($module === 'auth' || $module === 'dashboard' || $module === 'admin') {
            return true;
        }

        $edition = self::getCurrentEdition();
        $allowedFeatures = self::getEditionFeatures($edition);

        if (!$allowedFeatures) {
            throw new Exception(sprintf('版本 [%s] 未配置功能边界', $edition), self::ERROR_FEATURE_OUT_OF_BOUNDARY);
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
            throw new Exception(sprintf(
                '当前版本 [%s] 不支持该功能: %s，请升级版本',
                $edition,
                $featureKey
            ), self::ERROR_FEATURE_OUT_OF_BOUNDARY);
        }

        return true;
    }

    public static function validateUserQuota($currentUserCount) {
        $license = self::getActiveLicense();
        $maxUsers = $license['max_users'] ?? LICENSE_MAX_USERS;
        if ($currentUserCount >= $maxUsers) {
            self::recordViolation('user_limit_exceeded', [
                'current' => $currentUserCount,
                'limit' => $maxUsers
            ]);
            throw new Exception(sprintf(
                '用户数已达上限 (%d/%d)，请扩容',
                $currentUserCount,
                $maxUsers
            ), self::ERROR_USER_LIMIT_EXCEEDED);
        }
        return true;
    }

    public static function validateClientQuota($currentClientCount) {
        $license = self::getActiveLicense();
        $maxClients = $license['max_clients'] ?? LICENSE_MAX_CLIENTS;
        if ($currentClientCount >= $maxClients) {
            self::recordViolation('client_limit_exceeded', [
                'current' => $currentClientCount,
                'limit' => $maxClients
            ]);
            throw new Exception(sprintf(
                '客户数已达上限 (%d/%d)，请扩容',
                $currentClientCount,
                $maxClients
            ), self::ERROR_CLIENT_LIMIT_EXCEEDED);
        }
        return true;
    }

    public static function getCurrentEdition() {
        $license = self::getActiveLicense();
        return $license['edition_code'] ?? 'standard';
    }

    private static function getEditionFeatures($edition) {
        $boundary = COMMERCIAL_FEATURE_BOUNDARY;
        $features = [];
        $priority = ['standard', 'professional', 'enterprise'];
        foreach ($priority as $ed) {
            if (!isset($boundary[$ed])) continue;
            $features = array_merge($features, $boundary[$ed]);
            if ($ed === $edition) break;
        }
        return $features;
    }

    public static function getLicenseInfo() {
        $license = self::getActiveLicense();
        $expireStr = $license['expire'] ?? LICENSE_EXPIRE;
        $daysLeft = max(0, floor((strtotime($expireStr) - time()) / 86400));

        return [
            'key' => substr($license['license_key'] ?? LICENSE_KEY, 0, 12) . '***',
            'full_key' => $license['license_key'] ?? LICENSE_KEY,
            'edition' => $license['edition_label'] ?? '标准版',
            'edition_code' => $license['edition_code'] ?? 'standard',
            'expire' => $expireStr,
            'issued_at' => $license['issued_at'] ?? null,
            'days_left' => $daysLeft,
            'max_users' => $license['max_users'] ?? LICENSE_MAX_USERS,
            'max_clients' => $license['max_clients'] ?? LICENSE_MAX_CLIENTS,
            'features' => self::getEditionFeatures(self::getCurrentEdition()),
            'boundary' => COMMERCIAL_FEATURE_BOUNDARY,
            'updated_at' => $license['updated_at'] ?? null
        ];
    }

    private static function recordViolation($type, $data) {
        self::$violations[] = [
            'type' => $type,
            'data' => $data,
            'time' => date('Y-m-d H:i:s'),
            'uri' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
        ];
    }

    public static function getViolations() {
        return self::$violations;
    }
}
