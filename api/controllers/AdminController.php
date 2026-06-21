<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/LicenseStore.php';
require_once __DIR__ . '/../guard/RedLineGuard.php';
require_once __DIR__ . '/../guard/CommercialGuard.php';
require_once __DIR__ . '/../guard/PlatformGuard.php';

class AdminController {
    public function userList($input) {
        $page = max(1, (int)($input['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($input['page_size'] ?? 10)));

        $users = [
            ['id' => 1, 'username' => 'admin', 'name' => '系统管理员', 'role' => 'super_admin', 'role_label' => '超级管理员', 'status' => 'active', 'platform' => 'admin', 'last_login' => '2026-06-21 08:30:00', 'created_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'username' => 'sales01', 'name' => '张销售', 'role' => 'sales_manager', 'role_label' => '销售主管', 'status' => 'active', 'platform' => 'sales', 'last_login' => '2026-06-21 09:15:00', 'created_at' => '2026-01-10 00:00:00'],
            ['id' => 3, 'username' => 'sales02', 'name' => '李销售', 'role' => 'sales', 'role_label' => '销售代表', 'status' => 'active', 'platform' => 'sales', 'last_login' => '2026-06-20 17:45:00', 'created_at' => '2026-02-15 00:00:00'],
            ['id' => 4, 'username' => 'sales03', 'name' => '王销售', 'role' => 'sales', 'role_label' => '销售代表', 'status' => 'active', 'platform' => 'sales', 'last_login' => '2026-06-21 10:00:00', 'created_at' => '2026-03-01 00:00:00'],
            ['id' => 5, 'username' => 'sales04', 'name' => '赵销售', 'role' => 'sales', 'role_label' => '销售代表', 'status' => 'disabled', 'platform' => 'sales', 'last_login' => '2026-06-10 14:20:00', 'created_at' => '2026-03-20 00:00:00'],
            ['id' => 6, 'username' => 'client01', 'name' => '王客户', 'role' => 'client', 'role_label' => '客户用户', 'status' => 'active', 'platform' => 'client', 'last_login' => '2026-06-18 16:00:00', 'created_at' => '2026-04-01 00:00:00']
        ];

        $total = count($users);
        $offset = ($page - 1) * $pageSize;
        $list = array_slice($users, $offset, $pageSize);

        Response::success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'quota' => [
                'used' => $total,
                'limit' => LICENSE_MAX_USERS,
                'can_create' => $total < LICENSE_MAX_USERS
            ],
            'role_options' => [
                ['value' => 'super_admin', 'label' => '超级管理员'],
                ['value' => 'admin', 'label' => '系统管理员'],
                ['value' => 'sales_manager', 'label' => '销售主管'],
                ['value' => 'sales', 'label' => '销售代表'],
                ['value' => 'client', 'label' => '客户用户']
            ]
        ]);
    }

    public function userCreate($input) {
        $username = trim($input['username'] ?? '');
        $name = trim($input['name'] ?? '');
        $role = trim($input['role'] ?? '');
        $password = $input['password'] ?? '';

        if (!$username || !$name || !$role || !$password) {
            Response::badRequest('用户名、姓名、角色、密码不能为空');
        }

        try {
            CommercialGuard::validateUserQuota(6);
        } catch (Exception $e) {
            Response::commercialBlock(
                $e->getCode() ?: CommercialGuard::ERROR_USER_LIMIT_EXCEEDED,
                $e->getMessage(),
                CommercialGuard::getViolations()
            );
        }

        Response::success([
            'id' => 7,
            'username' => $username,
            'name' => $name,
            'role' => $role,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ], '用户创建成功');
    }

    public function licenseInfo($input) {
        Response::success([
            'license' => CommercialGuard::getLicenseInfo(),
            'active_detail' => LicenseStore::getActive(),
            'list_summary' => [
                'total' => count(LicenseStore::getList()),
                'active_key' => LicenseStore::getActiveLicenseKey()
            ],
            'violations' => CommercialGuard::getViolations(),
            'redline_status' => [
                'ip_whitelist' => RED_LINE_IP_WHITELIST,
                'access_hours' => RED_LINE_ACCESS_HOURS,
                'rate_limit' => RED_LINE_MAX_REQUESTS_PER_MINUTE,
                'session_timeout' => RED_LINE_SESSION_TIMEOUT,
                'current_platform' => PlatformGuard::getCurrentPlatform(),
                'platform_audit' => PlatformGuard::getAuditLog()
            ]
        ]);
    }

    public function licenseVerify($input) {
        $key = trim($input['license_key'] ?? '');
        $remark = trim($input['remark'] ?? '');
        if (!$key) {
            Response::badRequest('License Key 不能为空');
        }

        if (!LicenseStore::verifySignature($key)) {
            Response::error(400, 'License Key 格式无效', ['valid' => false]);
        }

        $saved = LicenseStore::save($key, [
            'remark' => $remark,
            'expire' => date('Y-m-d', strtotime('+1 year'))
        ]);

        if (!$saved) {
            Response::error(500, 'License 保存失败');
        }

        CommercialGuard::refreshActiveLicense();

        Response::success([
            'valid' => true,
            'saved' => true,
            'license_key' => $key,
            'edition' => $saved['edition_label'],
            'edition_code' => $saved['edition_code'],
            'expire' => $saved['expire'],
            'issued_at' => $saved['issued_at'],
            'max_users' => $saved['max_users'],
            'max_clients' => $saved['max_clients'],
            'features' => $saved['features'],
            'remark' => $saved['remark'],
            'updated_at' => $saved['updated_at'],
            'is_active' => true,
            'detail' => LicenseStore::getDetail($key)
        ], 'License 校验通过并已保存激活');
    }

    public function licenseList($input) {
        $list = LicenseStore::getList();
        $activeKey = LicenseStore::getActiveLicenseKey();

        Response::success([
            'list' => $list,
            'total' => count($list),
            'active_key' => $activeKey,
            'current_license' => CommercialGuard::getLicenseInfo()
        ]);
    }

    public function licenseDetail($input) {
        $key = trim($input['license_key'] ?? '');
        if (!$key) {
            Response::badRequest('license_key 不能为空');
        }

        $detail = LicenseStore::getDetail($key);
        if (!$detail) {
            Response::notFound('License 记录不存在');
        }

        Response::success([
            'detail' => $detail,
            'current_license' => CommercialGuard::getLicenseInfo()
        ]);
    }

    public function licenseActivate($input) {
        $key = trim($input['license_key'] ?? '');
        if (!$key) {
            Response::badRequest('license_key 不能为空');
        }

        if (!LicenseStore::verifySignature($key)) {
            Response::error(400, 'License Key 格式无效');
        }

        $ok = LicenseStore::setActive($key);
        if (!$ok) {
            Response::notFound('License 记录不存在，请先验证保存');
        }

        CommercialGuard::refreshActiveLicense();

        Response::success([
            'license_key' => $key,
            'is_active' => true,
            'detail' => LicenseStore::getDetail($key),
            'current_license' => CommercialGuard::getLicenseInfo()
        ], 'License 已切换激活');
    }

    public function auditLogs($input) {
        $page = max(1, (int)($input['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($input['page_size'] ?? 20)));

        $logs = [
            ['id' => 1001, 'user' => 'admin', 'action' => 'user_login', 'action_label' => '登录系统', 'platform' => 'admin', 'ip' => '127.0.0.1', 'device' => 'Chrome/Mac', 'status' => 'success', 'time' => '2026-06-21 08:30:00'],
            ['id' => 1002, 'user' => 'sales01', 'action' => 'user_login', 'action_label' => '登录系统', 'platform' => 'sales', 'ip' => '192.168.1.105', 'device' => 'Chrome/Windows', 'status' => 'success', 'time' => '2026-06-21 09:15:00'],
            ['id' => 1003, 'user' => 'admin', 'action' => 'customer_create', 'action_label' => '创建客户', 'platform' => 'admin', 'ip' => '127.0.0.1', 'device' => 'Chrome/Mac', 'status' => 'success', 'time' => '2026-06-21 09:00:00'],
            ['id' => 1004, 'user' => 'sales01', 'action' => 'customer_update', 'action_label' => '更新客户', 'platform' => 'sales', 'ip' => '192.168.1.105', 'device' => 'Chrome/Windows', 'status' => 'success', 'time' => '2026-06-21 09:30:00'],
            ['id' => 1005, 'user' => 'unknown', 'action' => 'redline_block', 'action_label' => '红线拦截-设备指纹异常', 'platform' => 'sales', 'ip' => '45.33.32.156', 'device' => 'Unknown', 'status' => 'blocked', 'time' => '2026-06-21 08:55:00'],
            ['id' => 1006, 'user' => 'client01', 'action' => 'user_login', 'action_label' => '登录系统', 'platform' => 'client', 'ip' => '114.88.12.36', 'device' => 'Safari/iOS', 'status' => 'success', 'time' => '2026-06-20 16:00:00']
        ];

        $total = count($logs);
        $offset = ($page - 1) * $pageSize;
        $list = array_slice($logs, $offset, $pageSize);

        Response::success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize
        ]);
    }

    public function redlineConfig($input) {
        $user = RedLineGuard::getCurrentUser();
        if (!$user || !in_array($user['role'] ?? '', ['super_admin', 'admin'])) {
            Response::forbidden('无权限查看红线配置');
        }

        $allConfigs = RedLineGuard::getAllPlatformRedLineStatus();
        $currentStatus = RedLineGuard::getPlatformRedLineStatus();

        Response::success([
            'all_platforms' => $allConfigs,
            'current_platform' => $currentStatus,
            'platform_options' => [
                ['value' => 'admin', 'label' => '管理端'],
                ['value' => 'sales', 'label' => '销售端'],
                ['value' => 'client', 'label' => '客户端']
            ],
            'global_default' => [
                'ip_whitelist' => RED_LINE_IP_WHITELIST,
                'access_hours' => RED_LINE_ACCESS_HOURS,
                'max_requests_per_minute' => RED_LINE_MAX_REQUESTS_PER_MINUTE,
                'session_timeout' => RED_LINE_SESSION_TIMEOUT
            ]
        ]);
    }

    public function redlineUpdate($input) {
        $user = RedLineGuard::getCurrentUser();
        if (!$user || !in_array($user['role'] ?? '', ['super_admin', 'admin'])) {
            Response::forbidden('无权限修改红线配置');
        }

        $platform = trim($input['platform'] ?? '');
        if (!in_array($platform, PlatformGuard::PLATFORM_TYPES)) {
            Response::badRequest('无效的平台类型');
        }

        $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : true;
        $ipWhitelist = $input['ip_whitelist'] ?? [];
        $ipWhitelistEnforce = isset($input['ip_whitelist_enforce']) ? (bool)$input['ip_whitelist_enforce'] : false;
        $accessHours = $input['access_hours'] ?? ['start' => '00:00', 'end' => '23:59'];
        $accessHoursEnforce = isset($input['access_hours_enforce']) ? (bool)$input['access_hours_enforce'] : false;
        $maxRequestsPerMinute = (int)($input['max_requests_per_minute'] ?? 300);
        $sessionTimeout = (int)($input['session_timeout'] ?? 7200);
        $requireDeviceFingerprint = isset($input['require_device_fingerprint']) ? (bool)$input['require_device_fingerprint'] : false;
        $deviceFingerprintThreshold = (float)($input['device_fingerprint_threshold'] ?? 0.6);
        $allowMultiDeviceLogin = isset($input['allow_multi_device_login']) ? (bool)$input['allow_multi_device_login'] : true;
        $sensitiveOperation2fa = isset($input['sensitive_operation_2fa']) ? (bool)$input['sensitive_operation_2fa'] : false;

        if (!is_array($ipWhitelist)) {
            Response::badRequest('IP白名单格式错误');
        }

        if (!is_array($accessHours) || !isset($accessHours['start']) || !isset($accessHours['end'])) {
            Response::badRequest('访问时段格式错误');
        }

        if ($maxRequestsPerMinute < 1 || $maxRequestsPerMinute > 10000) {
            Response::badRequest('请求频率限制必须在1-10000之间');
        }

        if ($sessionTimeout < 60 || $sessionTimeout > 86400 * 30) {
            Response::badRequest('会话超时时间必须在60秒-30天之间');
        }

        if ($deviceFingerprintThreshold < 0 || $deviceFingerprintThreshold > 1) {
            Response::badRequest('设备指纹阈值必须在0-1之间');
        }

        $platformConfigs = RED_LINE_PLATFORM_CONFIG;
        $platformConfigs[$platform] = [
            'enabled' => $enabled,
            'ip_whitelist' => $ipWhitelist,
            'ip_whitelist_enforce' => $ipWhitelistEnforce,
            'access_hours' => $accessHours,
            'access_hours_enforce' => $accessHoursEnforce,
            'max_requests_per_minute' => $maxRequestsPerMinute,
            'session_timeout' => $sessionTimeout,
            'require_device_fingerprint' => $requireDeviceFingerprint,
            'device_fingerprint_threshold' => $deviceFingerprintThreshold,
            'allow_multi_device_login' => $allowMultiDeviceLogin,
            'sensitive_operation_2fa' => $sensitiveOperation2fa
        ];

        Response::success([
            'platform' => $platform,
            'config' => $platformConfigs[$platform],
            'message' => '红线配置已更新（注：当前为模拟模式，实际使用需持久化到数据库）'
        ], '红线配置更新成功');
    }
}
