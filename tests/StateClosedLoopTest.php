<?php

declare(strict_types=1);

final class StateClosedLoopTest extends TestCase
{
    private function runFullChain(): void
    {
        PlatformGuard::validate();
        CommercialGuard::validate();
        RedLineGuard::validate();
    }

    private function fullPassSetup(string $platform, string $uri): void
    {
        $this->mockRequest([
            'REQUEST_URI' => $uri,
            PlatformGuard::HEADER_PLATFORM => $platform,
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'CRM-UnitTest/1.0',
            'HTTP_ACCEPT_LANGUAGE' => 'zh-CN',
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ]);
        $this->setToken($this->makeToken($platform));
        $this->setDeviceFingerprint($this->serverFingerprint());
    }

    private function makeToken(string $platform): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'user_id' => 1,
            'username' => 'loop_user',
            'role' => $platform,
            'platform' => $platform,
            'iat' => time(),
            'exp' => time() + 7200,
            'session_id' => 'loop_' . uniqid(),
        ]));
        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
        return "$header.$payload.$signature";
    }

    private function serverFingerprint(): string
    {
        return md5(implode('|', [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]));
    }

    public function test_state_propagation_admin_platform_to_redline(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/customer/basic',
            PlatformGuard::HEADER_PLATFORM => 'admin',
        ]);
        PlatformGuard::validate();
        $this->assertSame('admin', PlatformGuard::getCurrentPlatform());

        $config = RedLineGuard::getPlatformConfig();
        $this->assertTrue($config['require_device_fingerprint']);
        $this->assertFalse($config['allow_multi_device_login']);
    }

    public function test_state_propagation_sales_platform_to_redline(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/customer/basic',
            PlatformGuard::HEADER_PLATFORM => 'sales',
        ]);
        PlatformGuard::validate();
        $this->assertSame('sales', PlatformGuard::getCurrentPlatform());

        $config = RedLineGuard::getPlatformConfig();
        $this->assertFalse($config['require_device_fingerprint']);
        $this->assertTrue($config['allow_multi_device_login']);
    }

    public function test_state_propagation_client_platform_to_redline(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/auth/check',
            PlatformGuard::HEADER_PLATFORM => 'client',
        ]);
        PlatformGuard::validate();
        $this->assertSame('client', PlatformGuard::getCurrentPlatform());

        $config = RedLineGuard::getPlatformConfig();
        $this->assertSame(60, $config['max_requests_per_minute']);
        $this->assertSame(86400, $config['session_timeout']);
    }

    public function test_chain_blocked_when_platform_missing(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/customer/list']);
        unset($_SERVER[PlatformGuard::HEADER_PLATFORM]);

        $caught = null;
        try {
            $this->runFullChain();
        } catch (Exception $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(4001, $caught->getCode());
        $this->assertGreaterThanOrEqual(1, count(PlatformGuard::getViolations()));
        $this->assertCount(0, CommercialGuard::getViolations());
        $this->assertCount(0, RedLineGuard::getRedLineEvents());
    }

    public function test_chain_blocked_when_platform_boundary_violated(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/admin/user/list',
            PlatformGuard::HEADER_PLATFORM => 'sales',
        ]);

        $caught = null;
        try {
            $this->runFullChain();
        } catch (Exception $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(4003, $caught->getCode());
        $this->assertCount(0, CommercialGuard::getViolations());
        $this->assertCount(0, RedLineGuard::getRedLineEvents());
    }

    public function test_chain_blocked_when_commercial_feature_out_of_boundary(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/opportunity/list',
            PlatformGuard::HEADER_PLATFORM => 'sales',
        ]);

        $caught = null;
        try {
            $this->runFullChain();
        } catch (Exception $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $code = $caught->getCode();
        $this->assertTrue($code >= 4100 && $code <= 4199);
        $this->assertGreaterThanOrEqual(1, count(CommercialGuard::getViolations()));
        $this->assertCount(0, RedLineGuard::getRedLineEvents());
    }

    public function test_chain_reaches_redline_when_platform_and_commercial_pass(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/customer/basic',
            PlatformGuard::HEADER_PLATFORM => 'sales',
        ]);

        $caught = null;
        try {
            $this->runFullChain();
        } catch (Exception $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $code = $caught->getCode();
        $this->assertTrue($code >= 4200 && $code <= 4299, "Expected redline error code, got $code");
        $this->assertGreaterThanOrEqual(1, count(RedLineGuard::getRedLineEvents()));
    }

    public function test_full_chain_passes_for_admin(): void
    {
        $this->fullPassSetup('admin', '/api/customer/basic');
        $this->assertNoException(fn() => $this->runFullChain());
        $this->assertSame('admin', PlatformGuard::getCurrentPlatform());
        $this->assertContains('platform_access', array_column(PlatformGuard::getAuditLog(), 'action'));
        $this->assertTrue(RedLineGuard::getStatus()['pass']);
    }

    public function test_full_chain_passes_for_sales(): void
    {
        $this->fullPassSetup('sales', '/api/customer/basic');
        $this->assertNoException(fn() => $this->runFullChain());
        $this->assertSame('sales', PlatformGuard::getCurrentPlatform());
        $this->assertTrue(RedLineGuard::getStatus()['pass']);
    }

    public function test_full_chain_passes_for_client(): void
    {
        $this->fullPassSetup('client', '/api/auth/check');
        $this->assertNoException(fn() => $this->runFullChain());
        $this->assertSame('client', PlatformGuard::getCurrentPlatform());
        $this->assertTrue(RedLineGuard::getStatus()['pass']);
    }

    public function test_error_code_taxonomy_platform_range(): void
    {
        $reflection = new ReflectionClass(PlatformGuard::class);
        $constants = $reflection->getConstants();
        $codes = array_filter($constants, fn($k) => str_starts_with($k, 'ERROR_'), ARRAY_FILTER_USE_KEY);
        foreach ($codes as $name => $code) {
            $this->assertTrue($code >= 4001 && $code <= 4099,
                "Platform error $name=$code must be in 4001-4099 range");
        }
    }

    public function test_error_code_taxonomy_commercial_range(): void
    {
        $reflection = new ReflectionClass(CommercialGuard::class);
        $constants = $reflection->getConstants();
        $codes = array_filter($constants, fn($k) => str_starts_with($k, 'ERROR_'), ARRAY_FILTER_USE_KEY);
        foreach ($codes as $name => $code) {
            $this->assertTrue($code >= 4101 && $code <= 4199,
                "Commercial error $name=$code must be in 4101-4199 range");
        }
    }

    public function test_error_code_taxonomy_redline_range(): void
    {
        $reflection = new ReflectionClass(RedLineGuard::class);
        $constants = $reflection->getConstants();
        $codes = array_filter($constants, fn($k) => str_starts_with($k, 'ERROR_'), ARRAY_FILTER_USE_KEY);
        foreach ($codes as $name => $code) {
            $this->assertTrue($code >= 4201 && $code <= 4299,
                "RedLine error $name=$code must be in 4201-4299 range");
        }
    }

    public function test_error_code_ranges_do_not_overlap(): void
    {
        $platformCodes = array_filter((new ReflectionClass(PlatformGuard::class))->getConstants(),
            fn($k) => str_starts_with($k, 'ERROR_'), ARRAY_FILTER_USE_KEY);
        $commercialCodes = array_filter((new ReflectionClass(CommercialGuard::class))->getConstants(),
            fn($k) => str_starts_with($k, 'ERROR_'), ARRAY_FILTER_USE_KEY);
        $redLineCodes = array_filter((new ReflectionClass(RedLineGuard::class))->getConstants(),
            fn($k) => str_starts_with($k, 'ERROR_'), ARRAY_FILTER_USE_KEY);

        $platformValues = array_values($platformCodes);
        $commercialValues = array_values($commercialCodes);
        $redLineValues = array_values($redLineCodes);

        $all = array_merge($platformValues, $commercialValues, $redLineValues);
        $this->assertSame(count($all), count(array_unique($all)),
            'Error codes across three guards must be unique');
    }

    public function test_public_endpoints_bypass_all_three_guards(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/auth/login']);
        $this->assertNoException(fn() => $this->runFullChain());
    }

    public function test_three_platforms_have_distinct_redline_configs(): void
    {
        $all = RedLineGuard::getAllPlatformRedLineStatus();
        $this->assertNotSame(
            $all['admin']['max_requests_per_minute'],
            $all['sales']['max_requests_per_minute']
        );
        $this->assertNotSame(
            $all['sales']['max_requests_per_minute'],
            $all['client']['max_requests_per_minute']
        );
        $this->assertNotSame(
            $all['admin']['session_timeout'],
            $all['client']['session_timeout']
        );
    }

    public function test_platform_guard_sets_state_consumed_by_redline(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/customer/basic',
            PlatformGuard::HEADER_PLATFORM => 'admin',
        ]);
        PlatformGuard::validate();

        $platformFromGuard = PlatformGuard::getCurrentPlatform();
        $configFromRedLine = RedLineGuard::getPlatformConfig();

        $this->assertSame('admin', $platformFromGuard);
        $this->assertTrue($configFromRedLine['enabled']);
        $this->assertTrue($configFromRedLine['require_device_fingerprint']);

        $status = RedLineGuard::getPlatformRedLineStatus();
        $this->assertSame($platformFromGuard, $status['platform']);
    }

    public function test_chain_order_is_platform_then_commercial_then_redline(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/customer/basic',
            PlatformGuard::HEADER_PLATFORM => 'sales',
        ]);

        $platformPassed = false;
        $commercialPassed = false;
        $redLineReached = false;

        try {
            PlatformGuard::validate();
            $platformPassed = true;
            CommercialGuard::validate();
            $commercialPassed = true;
            RedLineGuard::validate();
            $redLineReached = true;
        } catch (Exception $e) {
        }

        $this->assertTrue($platformPassed, 'PlatformGuard must pass first');
        $this->assertTrue($commercialPassed, 'CommercialGuard must pass second');
        $this->assertFalse($redLineReached, 'RedLineGuard should not be reached (missing token)');
    }
}
