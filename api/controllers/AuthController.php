<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../guard/RedLineGuard.php';
require_once __DIR__ . '/../guard/PlatformGuard.php';
require_once __DIR__ . '/../guard/CommercialGuard.php';

class AuthController {
    public function platformInfo() {
        $globalDefault = [
            'ip_whitelist_enabled' => !empty(RED_LINE_IP_WHITELIST),
            'access_hours' => RED_LINE_ACCESS_HOURS,
            'rate_limit' => RED_LINE_MAX_REQUESTS_PER_MINUTE . '/分钟',
            'session_timeout' => RED_LINE_SESSION_TIMEOUT . '秒'
        ];

        $persisted = RedLineGuard::loadPersistedConfigs();
        $hasPersisted = $persisted && isset($persisted['configs']);
        $allPlatforms = RedLineGuard::getAllPlatformRedLineStatus();
        $platformSpecific = [];

        foreach ($allPlatforms as $p => $cfg) {
            $platformSpecific[$p] = [
                'enabled' => $cfg['enabled'],
                'ip_whitelist' => $cfg['ip_whitelist'],
                'ip_whitelist_enforce' => $cfg['ip_whitelist_enforce'],
                'access_hours' => $cfg['access_hours'],
                'access_hours_enforce' => $cfg['access_hours_enforce'],
                'max_requests_per_minute' => $cfg['max_requests_per_minute'],
                'session_timeout' => $cfg['session_timeout']
            ];
        }

        Response::success([
            'platform' => PlatformGuard::getPlatformInfo(),
            'license' => CommercialGuard::getLicenseInfo(),
            'redline_status' => $globalDefault,
            'redline_platform_config' => $platformSpecific,
            'redline_persisted' => $hasPersisted
        ]);
    }

    public function login($input) {
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $platform = strtolower($input['platform'] ?? 'sales');
        $deviceFingerprint = $_SERVER['HTTP_X_DEVICE_FINGERPRINT'] ?? '';

        if (!$username || !$password) {
            Response::badRequest('用户名和密码不能为空');
        }

        if (!in_array($platform, ['admin', 'sales', 'client'])) {
            Response::error(400, '无效的平台入口类型');
        }

        $users = $this->getMockUsers();
        $user = null;
        foreach ($users as $u) {
            if ($u['username'] === $username && $u['password'] === $password) {
                if ($u['platform'] === $platform || $u['platform'] === '*') {
                    $user = $u;
                    break;
                }
            }
        }

        if (!$user) {
            Response::error(401, '用户名或密码错误，或无权访问该入口');
        }

        $token = RedLineGuard::generateToken(
            $user['id'],
            $user['username'],
            $user['role'],
            $platform
        );

        Response::success([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'role' => $user['role'],
                'platform' => $platform,
                'avatar' => $user['avatar'] ?? ''
            ],
            'platform' => $platform,
            'device_fp' => $deviceFingerprint ? substr(md5($deviceFingerprint), 0, 16) : null
        ], '登录成功');
    }

    public function logout($input) {
        Response::success(null, '已退出登录');
    }

    public function refresh($input) {
        $user = RedLineGuard::getCurrentUser();
        if (!$user) {
            Response::unauthorized('当前 Token 无效，无法刷新');
        }

        $newToken = RedLineGuard::generateToken(
            $user['user_id'],
            $user['username'],
            $user['role'],
            $user['platform']
        );

        Response::success([
            'token' => $newToken,
            'expires_in' => JWT_EXPIRE
        ], 'Token 刷新成功');
    }

    public function check($input) {
        $user = RedLineGuard::getCurrentUser();
        if (!$user) {
            Response::unauthorized();
        }
        Response::success([
            'user' => $user,
            'redline' => RedLineGuard::getStatus(),
            'platform' => PlatformGuard::getCurrentPlatform()
        ], '登录状态有效');
    }

    private function getMockUsers() {
        return [
            [
                'id' => 1,
                'username' => 'admin',
                'password' => 'admin123',
                'name' => '系统管理员',
                'role' => 'super_admin',
                'platform' => 'admin',
                'avatar' => ''
            ],
            [
                'id' => 2,
                'username' => 'sales01',
                'password' => 'sales123',
                'name' => '张销售',
                'role' => 'sales_manager',
                'platform' => 'sales',
                'avatar' => ''
            ],
            [
                'id' => 3,
                'username' => 'sales02',
                'password' => 'sales123',
                'name' => '李销售',
                'role' => 'sales',
                'platform' => 'sales',
                'avatar' => ''
            ],
            [
                'id' => 4,
                'username' => 'client01',
                'password' => 'client123',
                'name' => '王客户',
                'role' => 'client',
                'platform' => 'client',
                'avatar' => ''
            ]
        ];
    }
}
