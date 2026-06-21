<?php
class CommercialGuard {
    const ERROR_LICENSE_INVALID = 4101;
    const ERROR_LICENSE_EXPIRED = 4102;
    const ERROR_USER_LIMIT_EXCEEDED = 4103;
    const ERROR_CLIENT_LIMIT_EXCEEDED = 4104;
    const ERROR_FEATURE_OUT_OF_BOUNDARY = 4105;
    const ERROR_TRIAL_EXPIRED = 4106;

    private static $violations = [];

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

    private static function validateLicense() {
        $licenseKey = self::getRequestLicenseKey();
        if (!$licenseKey) {
            $licenseKey = LICENSE_KEY;
        }

        if (!self::verifyLicenseSignature($licenseKey)) {
            self::recordViolation('invalid_license', ['key' => substr($licenseKey, 0, 8) . '***']);
            throw new Exception('License 签名校验失败，系统未授权', self::ERROR_LICENSE_INVALID);
        }
    }

    private static function validateExpiration() {
        $expireDate = strtotime(LICENSE_EXPIRE);
        $today = time();

        if ($today > $expireDate) {
            self::recordViolation('license_expired', [
                'expired_at' => LICENSE_EXPIRE,
                'days_overdue' => floor(($today - $expireDate) / 86400)
            ]);
            throw new Exception(sprintf(
                'License 已过期 (%s)，请联系商务续费',
                LICENSE_EXPIRE
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

        $bypassModules = ['auth', 'dashboard'];
        if (!$featureMatched && !in_array($module, $bypassModules)) {
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
        if ($currentUserCount >= LICENSE_MAX_USERS) {
            self::recordViolation('user_limit_exceeded', [
                'current' => $currentUserCount,
                'limit' => LICENSE_MAX_USERS
            ]);
            throw new Exception(sprintf(
                '用户数已达上限 (%d/%d)，请扩容',
                $currentUserCount,
                LICENSE_MAX_USERS
            ), self::ERROR_USER_LIMIT_EXCEEDED);
        }
        return true;
    }

    public static function validateClientQuota($currentClientCount) {
        if ($currentClientCount >= LICENSE_MAX_CLIENTS) {
            self::recordViolation('client_limit_exceeded', [
                'current' => $currentClientCount,
                'limit' => LICENSE_MAX_CLIENTS
            ]);
            throw new Exception(sprintf(
                '客户数已达上限 (%d/%d)，请扩容',
                $currentClientCount,
                LICENSE_MAX_CLIENTS
            ), self::ERROR_CLIENT_LIMIT_EXCEEDED);
        }
        return true;
    }

    private static function verifyLicenseSignature($licenseKey) {
        $pattern = '/^CRM-LICENSE-\d{4}-(STD|PRO|ENT)$/';
        return (bool)preg_match($pattern, $licenseKey);
    }

    private static function getRequestLicenseKey() {
        return $_SERVER['HTTP_X_LICENSE_KEY'] ?? null;
    }

    private static function getCurrentEdition() {
        $key = LICENSE_KEY;
        if (strpos($key, '-ENT') !== false) return 'enterprise';
        if (strpos($key, '-PRO') !== false) return 'professional';
        return 'standard';
    }

    private static function getEditionFeatures($edition) {
        $boundary = COMMERCIAL_FEATURE_BOUNDARY;
        $features = [];
        foreach ($boundary as $ed => $feats) {
            $features = array_merge($features, $feats);
            if ($ed === $edition) break;
        }
        return $features;
    }

    public static function getLicenseInfo() {
        return [
            'key' => substr(LICENSE_KEY, 0, 12) . '***',
            'edition' => self::getCurrentEdition(),
            'expire' => LICENSE_EXPIRE,
            'days_left' => max(0, floor((strtotime(LICENSE_EXPIRE) - time()) / 86400)),
            'max_users' => LICENSE_MAX_USERS,
            'max_clients' => LICENSE_MAX_CLIENTS,
            'features' => self::getEditionFeatures(self::getCurrentEdition()),
            'boundary' => COMMERCIAL_FEATURE_BOUNDARY
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
