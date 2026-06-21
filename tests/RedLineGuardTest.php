<?php

declare(strict_types=1);

final class RedLineGuardTest extends TestCase
{
    private function invokePrivate(string $method, array $args = [])
    {
        $ref = new ReflectionMethod(RedLineGuard::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs(null, $args);
    }

    private function setPlatformConfigCache(string $platform, array $config): void
    {
        $ref = new ReflectionProperty(RedLineGuard::class, 'platformConfigCache');
        $ref->setAccessible(true);
        $cache = $ref->getValue() ?? [];
        $cache[$platform] = $config;
        $ref->setValue(null, $cache);
    }

    private function setRequestCacheUser(array $user): void
    {
        $ref = new ReflectionProperty(RedLineGuard::class, 'requestCache');
        $ref->setAccessible(true);
        $cache = $ref->getValue();
        $cache['user'] = $user;
        $ref->setValue(null, $cache);
    }

    private function setupPlatform(string $platform, string $uri = '/api/customer/list'): void
    {
        $this->mockRequest([
            'REQUEST_URI' => $uri,
            PlatformGuard::HEADER_PLATFORM => $platform,
        ]);
        PlatformGuard::validate();
    }

    private function serverFingerprint(): string
    {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ];
        return md5(implode('|', $components));
    }

    private function makeToken(string $platform, array $overrides = []): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = array_merge([
            'user_id' => 1,
            'username' => 'testuser',
            'role' => $platform === 'admin' ? 'super_admin' : ($platform === 'sales' ? 'sales' : 'client'),
            'platform' => $platform,
            'iat' => time(),
            'exp' => time() + 7200,
            'session_id' => 'sess_' . uniqid(),
        ], $overrides);
        $payloadEncoded = base64_encode(json_encode($payload));
        $signature = base64_encode(hash_hmac('sha256', "$header.$payloadEncoded", JWT_SECRET, true));
        return "$header.$payloadEncoded.$signature";
    }

    private function makeForgedToken(): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'user_id' => 1, 'username' => 'hacker', 'role' => 'admin',
            'platform' => 'admin', 'iat' => time(), 'exp' => time() + 7200,
            'session_id' => 'forged'
        ]));
        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", 'wrong-secret', true));
        return "$header.$payload.$signature";
    }

    public function test_whitelisted_login_endpoint_passes_without_token(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/auth/login']);
        $this->assertNoException(fn() => RedLineGuard::validate());
    }

    public function test_whitelisted_platform_info_endpoint_passes_without_token(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/auth/platform-info']);
        $this->assertNoException(fn() => RedLineGuard::validate());
    }

    public function test_whitelisted_refresh_endpoint_passes_without_token(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/auth/refresh']);
        $this->assertNoException(fn() => RedLineGuard::validate());
    }

    public function test_non_whitelisted_without_platform_throws_4211(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/customer/list']);
        $this->assertErrorCode(4211, fn() => RedLineGuard::validate());
        $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
        $this->assertContains('platform_missing', $types);
    }

    public function test_platform_disabled_throws_4210(): void
    {
        $this->setupPlatform('admin');
        $this->setPlatformConfigCache('admin', array_merge(
            RedLineGuard::getPlatformConfig('admin'),
            ['enabled' => false]
        ));
        $this->assertErrorCode(4210, fn() => RedLineGuard::validate());
        $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
        $this->assertContains('platform_disabled', $types);
    }

    public function test_identity_missing_token_throws_4201(): void
    {
        $this->setupPlatform('sales');
        $this->setUri('/api/customer/list');
        $this->assertErrorCode(4201, fn() => $this->invokePrivate('validateIdentity'));
        $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
        $this->assertContains('identity_missing', $types);
    }

    public function test_forged_token_throws_4207(): void
    {
        $this->setupPlatform('sales');
        $this->setToken($this->makeForgedToken());
        $this->assertErrorCode(4207, fn() => $this->invokePrivate('validateIdentity'));
        $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
        $this->assertContains('token_invalid', $types);
    }

    public function test_expired_token_throws_4206(): void
    {
        $this->setupPlatform('sales');
        $token = $this->makeToken('sales', ['exp' => time() - 100]);
        $this->setToken($token);
        $this->assertErrorCode(4206, fn() => $this->invokePrivate('validateIdentity'));
        $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
        $this->assertContains('token_expired', $types);
    }

    public function test_token_payload_missing_exp_throws_4214(): void
    {
        $this->setupPlatform('sales');
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'user_id' => 1, 'username' => 'u', 'role' => 'sales', 'platform' => 'sales'
        ]));
        $sig = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
        $this->setToken("$header.$payload.$sig");
        $this->assertErrorCode(4214, fn() => $this->invokePrivate('validateIdentity'));
    }

    public function test_token_platform_mismatch_throws_4201(): void
    {
        $this->setupPlatform('sales');
        $this->setToken($this->makeToken('admin'));
        $this->assertErrorCode(4201, fn() => $this->invokePrivate('validateIdentity'));
        $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
        $this->assertContains('platform_mismatch', $types);
    }

    public function test_valid_token_passes_identity_for_sales(): void
    {
        $this->setupPlatform('sales');
        $this->setToken($this->makeToken('sales'));
        $this->assertNoException(fn() => $this->invokePrivate('validateIdentity'));
        $this->assertNotNull(RedLineGuard::getCurrentUser());
    }

    public function test_valid_token_passes_identity_for_admin(): void
    {
        $this->setupPlatform('admin');
        $this->setToken($this->makeToken('admin'));
        $this->assertNoException(fn() => $this->invokePrivate('validateIdentity'));
    }

    public function test_valid_token_passes_identity_for_client(): void
    {
        $this->setupPlatform('client', '/api/client/profile');
        $this->setToken($this->makeToken('client'));
        $this->assertNoException(fn() => $this->invokePrivate('validateIdentity'));
    }

    public function test_admin_device_fingerprint_required_throws_4209(): void
    {
        $this->setupPlatform('admin');
        unset($_SERVER['HTTP_X_DEVICE_FINGERPRINT']);
        $this->assertErrorCode(4209, fn() => $this->invokePrivate('validateDeviceFingerprint'));
        $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
        $this->assertContains('device_fingerprint_missing', $types);
    }

    public function test_admin_device_fingerprint_mismatch_throws_4202(): void
    {
        $this->setupPlatform('admin');
        $this->setDeviceFingerprint('0000000000000000000000000000ffff');
        $this->assertErrorCode(4202, fn() => $this->invokePrivate('validateDeviceFingerprint'));
        $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
        $this->assertContains('device_mismatch', $types);
    }

    public function test_admin_device_fingerprint_match_passes(): void
    {
        $this->setupPlatform('admin');
        $this->setDeviceFingerprint($this->serverFingerprint());
        $this->assertNoException(fn() => $this->invokePrivate('validateDeviceFingerprint'));
    }

    public function test_sales_does_not_require_device_fingerprint(): void
    {
        $this->setupPlatform('sales');
        unset($_SERVER['HTTP_X_DEVICE_FINGERPRINT']);
        $this->assertNoException(fn() => $this->invokePrivate('validateDeviceFingerprint'));
    }

    public function test_client_does_not_require_device_fingerprint(): void
    {
        $this->setupPlatform('client', '/api/client/profile');
        unset($_SERVER['HTTP_X_DEVICE_FINGERPRINT']);
        $this->assertNoException(fn() => $this->invokePrivate('validateDeviceFingerprint'));
    }

    public function test_admin_ip_in_whitelist_passes(): void
    {
        $this->setupPlatform('admin');
        $this->setClientIp('127.0.0.1');
        $this->assertNoException(fn() => $this->invokePrivate('validateIpAddress'));
    }

    public function test_admin_ip_not_in_whitelist_throws_4203(): void
    {
        $this->setupPlatform('admin');
        $this->setClientIp('8.8.8.8');
        $this->assertErrorCode(4203, fn() => $this->invokePrivate('validateIpAddress'));
        $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
        $this->assertContains('ip_blocked', $types);
    }

    public function test_admin_ip_in_cidr_range_passes(): void
    {
        $this->setupPlatform('admin');
        $this->setClientIp('192.168.1.100');
        $this->assertNoException(fn() => $this->invokePrivate('validateIpAddress'));
    }

    public function test_sales_ip_check_not_enforced(): void
    {
        $this->setupPlatform('sales');
        $this->setClientIp('8.8.8.8');
        $this->assertNoException(fn() => $this->invokePrivate('validateIpAddress'));
    }

    public function test_client_ip_check_not_enforced(): void
    {
        $this->setupPlatform('client', '/api/client/profile');
        $this->setClientIp('8.8.8.8');
        $this->assertNoException(fn() => $this->invokePrivate('validateIpAddress'));
    }

    public function test_admin_access_hours_not_enforced_by_default(): void
    {
        $this->setupPlatform('admin');
        $this->assertNoException(fn() => $this->invokePrivate('validateAccessHours'));
    }

    public function test_access_hours_enforced_outside_window_throws_4204(): void
    {
        $this->setupPlatform('admin');
        $config = RedLineGuard::getPlatformConfig('admin');
        $config['access_hours_enforce'] = true;
        $config['access_hours'] = ['start' => '00:00', 'end' => '00:01'];
        $this->setPlatformConfigCache('admin', $config);
        $now = date('H:i');
        if ($now > '00:01') {
            $this->assertErrorCode(4204, fn() => $this->invokePrivate('validateAccessHours'));
            $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
            $this->assertContains('outside_hours', $types);
        }
    }

    public function test_access_hours_enforced_within_window_passes(): void
    {
        $this->setupPlatform('admin');
        $config = RedLineGuard::getPlatformConfig('admin');
        $config['access_hours_enforce'] = true;
        $config['access_hours'] = ['start' => '00:00', 'end' => '23:59'];
        $this->setPlatformConfigCache('admin', $config);
        $this->assertNoException(fn() => $this->invokePrivate('validateAccessHours'));
    }

    public function test_rate_limit_under_limit_passes(): void
    {
        $this->setupPlatform('sales');
        $this->assertNoException(fn() => $this->invokePrivate('validateRateLimit'));
    }

    public function test_rate_limit_exceeded_throws_4205(): void
    {
        $this->setupPlatform('sales');
        $config = RedLineGuard::getPlatformConfig('sales');
        $limit = $config['max_requests_per_minute'];
        $identifier = 'rate_sales_127.0.0.1_' . date('YmdHi');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION[$identifier] = $limit;

        $this->assertErrorCode(4205, fn() => $this->invokePrivate('validateRateLimit'));
        $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
        $this->assertContains('rate_limit', $types);
    }

    public function test_session_first_access_passes(): void
    {
        $this->setupPlatform('sales');
        $this->setToken($this->makeToken('sales'));
        $this->invokePrivate('validateIdentity');
        $this->assertNoException(fn() => $this->invokePrivate('validateSession'));
    }

    public function test_session_timeout_throws_4206(): void
    {
        $this->setupPlatform('admin');
        $token = $this->makeToken('admin');
        $this->setToken($token);
        $this->invokePrivate('validateIdentity');
        $user = RedLineGuard::getCurrentUser();
        $sessionId = $user['session_id'] ?? 'default';
        $sessionKey = 'session_' . $sessionId;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION[$sessionKey . '_last_active'] = time() - 7200;

        $this->assertErrorCode(4206, fn() => $this->invokePrivate('validateSession'));
        $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
        $this->assertContains('session_timeout', $types);
    }

    public function test_multi_device_first_register_passes(): void
    {
        $this->setupPlatform('admin');
        $this->setToken($this->makeToken('admin'));
        $this->invokePrivate('validateIdentity');
        $this->setDeviceFingerprint($this->serverFingerprint());
        $this->assertNoException(fn() => $this->invokePrivate('validateMultiDeviceLogin'));
    }

    public function test_multi_device_login_blocked_for_admin_throws_4208(): void
    {
        $this->setupPlatform('admin');
        $this->setToken($this->makeToken('admin'));
        $this->invokePrivate('validateIdentity');
        $user = RedLineGuard::getCurrentUser();
        $userId = $user['user_id'] ?? 0;
        $deviceKey = 'device_fp_admin_' . $userId;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION[$deviceKey] = 'original_device_fingerprint_value';

        $this->setDeviceFingerprint('different_device_fingerprint_value');
        $this->assertErrorCode(4208, fn() => $this->invokePrivate('validateMultiDeviceLogin'));
        $types = array_map(fn($e) => $e['type'], RedLineGuard::getRedLineEvents());
        $this->assertContains('multi_device_login', $types);
    }

    public function test_multi_device_allowed_for_sales(): void
    {
        $this->setupPlatform('sales');
        $this->setToken($this->makeToken('sales'));
        $this->invokePrivate('validateIdentity');
        $this->setDeviceFingerprint('device_a');
        $this->assertNoException(fn() => $this->invokePrivate('validateMultiDeviceLogin'));
        $this->setDeviceFingerprint('device_b');
        $this->assertNoException(fn() => $this->invokePrivate('validateMultiDeviceLogin'));
    }

    public function test_multi_device_allowed_for_client(): void
    {
        $this->setupPlatform('client', '/api/client/profile');
        $this->setToken($this->makeToken('client'));
        $this->invokePrivate('validateIdentity');
        $this->setDeviceFingerprint('device_a');
        $this->assertNoException(fn() => $this->invokePrivate('validateMultiDeviceLogin'));
    }

    public function test_token_generation_and_verification_roundtrip(): void
    {
        $token = RedLineGuard::generateToken(42, 'tester', 'admin', 'admin');
        $payload = RedLineGuard::verifyToken($token);
        $this->assertTrue(is_array($payload));
        $this->assertSame(42, $payload['user_id']);
        $this->assertSame('tester', $payload['username']);
        $this->assertSame('admin', $payload['role']);
        $this->assertSame('admin', $payload['platform']);
        $this->assertTrue(isset($payload['exp']));
        $this->assertTrue(isset($payload['session_id']));
    }

    public function test_verify_token_rejects_malformed_token(): void
    {
        $this->assertFalse(RedLineGuard::verifyToken('not.a.valid'));
        $this->assertFalse(RedLineGuard::verifyToken('onlyonepart'));
        $this->assertFalse(RedLineGuard::verifyToken(''));
    }

    public function test_verify_token_rejects_tampered_signature(): void
    {
        $token = RedLineGuard::generateToken(1, 'u', 'admin', 'admin');
        $parts = explode('.', $token);
        $parts[2] = base64_encode(str_repeat('x', 32));
        $tampered = implode('.', $parts);
        $this->assertFalse(RedLineGuard::verifyToken($tampered));
    }

    public function test_get_platform_config_returns_admin_settings(): void
    {
        $this->setupPlatform('admin');
        $config = RedLineGuard::getPlatformConfig('admin');
        $this->assertTrue($config['enabled']);
        $this->assertTrue($config['require_device_fingerprint']);
        $this->assertFalse($config['allow_multi_device_login']);
        $this->assertSame(100, $config['max_requests_per_minute']);
        $this->assertSame(1800, $config['session_timeout']);
    }

    public function test_get_platform_config_returns_sales_settings(): void
    {
        $this->setupPlatform('sales');
        $config = RedLineGuard::getPlatformConfig('sales');
        $this->assertTrue($config['enabled']);
        $this->assertFalse($config['require_device_fingerprint']);
        $this->assertTrue($config['allow_multi_device_login']);
        $this->assertSame(200, $config['max_requests_per_minute']);
    }

    public function test_get_platform_config_returns_client_settings(): void
    {
        $this->setupPlatform('client', '/api/client/profile');
        $config = RedLineGuard::getPlatformConfig('client');
        $this->assertTrue($config['enabled']);
        $this->assertSame(60, $config['max_requests_per_minute']);
        $this->assertSame(86400, $config['session_timeout']);
    }

    public function test_get_all_platform_red_line_status(): void
    {
        $all = RedLineGuard::getAllPlatformRedLineStatus();
        $this->assertContains('admin', array_keys($all));
        $this->assertContains('sales', array_keys($all));
        $this->assertContains('client', array_keys($all));
        $this->assertTrue($all['admin']['require_device_fingerprint']);
        $this->assertFalse($all['sales']['require_device_fingerprint']);
    }

    public function test_red_line_events_are_recorded(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/customer/list']);
        try {
            RedLineGuard::validate();
        } catch (Exception $e) {
        }
        $events = RedLineGuard::getRedLineEvents();
        $this->assertGreaterThanOrEqual(1, count($events));
        $this->assertSame('platform_missing', $events[0]['type']);
    }

    public function test_get_status_returns_structure(): void
    {
        $this->setupPlatform('sales');
        $status = RedLineGuard::getStatus();
        $this->assertTrue($status['pass']);
        $this->assertTrue(isset($status['ip']));
        $this->assertTrue(isset($status['events_count']));
    }

    public function test_get_platform_red_line_status_includes_platform(): void
    {
        $this->setupPlatform('admin');
        $status = RedLineGuard::getPlatformRedLineStatus();
        $this->assertSame('admin', $status['platform']);
        $this->assertTrue($status['enabled']);
        $this->assertTrue(isset($status['platform_config']));
    }
}
