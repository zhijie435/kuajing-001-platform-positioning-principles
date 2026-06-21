<?php
require_once __DIR__ . '/../core/Response.php';
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
            Response::forbidden($e->getMessage());
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
        if (!$key) {
            Response::badRequest('License Key 不能为空');
        }

        $pattern = '/^CRM-LICENSE-\d{4}-(STD|PRO|ENT)$/';
        if (!preg_match($pattern, $key)) {
            Response::error(400, 'License Key 格式无效', ['valid' => false]);
        }

        $editionMap = ['STD' => '标准版', 'PRO' => '专业版', 'ENT' => '企业版'];
        $edition = substr($key, -3);

        Response::success([
            'valid' => true,
            'license_key' => $key,
            'edition' => $editionMap[$edition] ?? '标准版',
            'edition_code' => strtolower($edition),
            'expire' => date('Y-m-d', strtotime('+1 year')),
            'max_users' => $edition === 'ENT' ? 500 : ($edition === 'PRO' ? 200 : 100),
            'max_clients' => $edition === 'ENT' ? 100000 : ($edition === 'PRO' ? 50000 : 10000),
            'features' => CommercialGuard::getLicenseInfo()['features']
        ], 'License 校验通过');
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
}
