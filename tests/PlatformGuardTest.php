<?php

declare(strict_types=1);

final class PlatformGuardTest extends TestCase
{
    public function test_public_login_endpoint_bypasses_without_platform_header(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/auth/login']);
        unset($_SERVER[PlatformGuard::HEADER_PLATFORM]);
        $this->assertNoException(fn() => PlatformGuard::validate());
        $this->assertNull(PlatformGuard::getCurrentPlatform());
    }

    public function test_public_platform_info_endpoint_bypasses(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/auth/platform-info']);
        unset($_SERVER[PlatformGuard::HEADER_PLATFORM]);
        $this->assertNoException(fn() => PlatformGuard::validate());
    }

    public function test_public_refresh_endpoint_bypasses(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/auth/refresh']);
        unset($_SERVER[PlatformGuard::HEADER_PLATFORM]);
        $this->assertNoException(fn() => PlatformGuard::validate());
    }

    public function test_api_root_bypasses(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/']);
        unset($_SERVER[PlatformGuard::HEADER_PLATFORM]);
        $this->assertNoException(fn() => PlatformGuard::validate());
    }

    public function test_missing_platform_type_on_protected_endpoint_throws_4001(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/customer/list']);
        unset($_SERVER[PlatformGuard::HEADER_PLATFORM]);
        $this->assertErrorCode(4001, fn() => PlatformGuard::validate());
        $types = array_column(PlatformGuard::getViolations(), 'type');
        $this->assertContains('platform_type_missing', $types);
    }

    public function test_invalid_platform_type_throws_4002(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/customer/list',
            PlatformGuard::HEADER_PLATFORM => 'unknown',
        ]);
        $this->assertErrorCode(4002, fn() => PlatformGuard::validate());
        $types = array_column(PlatformGuard::getViolations(), 'type');
        $this->assertContains('platform_type_invalid', $types);
    }

    public function test_platform_type_is_case_insensitive(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/customer/list',
            PlatformGuard::HEADER_PLATFORM => 'ADMIN',
        ]);
        $this->assertNoException(fn() => PlatformGuard::validate());
        $this->assertSame('admin', PlatformGuard::getCurrentPlatform());
    }

    public function test_admin_accesses_admin_user_list(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/admin/user/list',
            PlatformGuard::HEADER_PLATFORM => 'admin',
        ]);
        $this->assertNoException(fn() => PlatformGuard::validate());
        $this->assertSame('admin', PlatformGuard::getCurrentPlatform());
    }

    public function test_admin_accesses_customer_list(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/customer/list',
            PlatformGuard::HEADER_PLATFORM => 'admin',
        ]);
        $this->assertNoException(fn() => PlatformGuard::validate());
    }

    public function test_sales_accesses_customer_list(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/customer/list',
            PlatformGuard::HEADER_PLATFORM => 'sales',
        ]);
        $this->assertNoException(fn() => PlatformGuard::validate());
        $this->assertSame('sales', PlatformGuard::getCurrentPlatform());
    }

    public function test_sales_accesses_followup_list(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/followup/list',
            PlatformGuard::HEADER_PLATFORM => 'sales',
        ]);
        $this->assertNoException(fn() => PlatformGuard::validate());
    }

    public function test_sales_accesses_dashboard_stats(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/dashboard/stats',
            PlatformGuard::HEADER_PLATFORM => 'sales',
        ]);
        $this->assertNoException(fn() => PlatformGuard::validate());
    }

    public function test_client_accesses_client_profile(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/client/profile',
            PlatformGuard::HEADER_PLATFORM => 'client',
        ]);
        $this->assertNoException(fn() => PlatformGuard::validate());
        $this->assertSame('client', PlatformGuard::getCurrentPlatform());
    }

    public function test_client_accesses_client_contracts(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/client/contract',
            PlatformGuard::HEADER_PLATFORM => 'client',
        ]);
        $this->assertNoException(fn() => PlatformGuard::validate());
    }

    public function test_sales_cannot_access_admin_endpoints_throws_4003(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/admin/user/list',
            PlatformGuard::HEADER_PLATFORM => 'sales',
        ]);
        $this->assertErrorCode(4003, fn() => PlatformGuard::validate());
        $types = array_column(PlatformGuard::getViolations(), 'type');
        $this->assertContains('platform_boundary_violation', $types);
    }

    public function test_client_cannot_access_admin_endpoints_throws_4003(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/admin/user/list',
            PlatformGuard::HEADER_PLATFORM => 'client',
        ]);
        $this->assertErrorCode(4003, fn() => PlatformGuard::validate());
    }

    public function test_admin_cannot_access_client_endpoints_throws_4003(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/client/profile',
            PlatformGuard::HEADER_PLATFORM => 'admin',
        ]);
        $this->assertErrorCode(4003, fn() => PlatformGuard::validate());
    }

    public function test_client_cannot_access_customer_module_throws_4003(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/customer/list',
            PlatformGuard::HEADER_PLATFORM => 'client',
        ]);
        $this->assertErrorCode(4003, fn() => PlatformGuard::validate());
    }

    public function test_admin_cannot_access_followup_module_throws_4003(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/followup/list',
            PlatformGuard::HEADER_PLATFORM => 'admin',
        ]);
        $this->assertErrorCode(4003, fn() => PlatformGuard::validate());
    }

    public function test_auth_module_always_allowed(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/auth/check',
            PlatformGuard::HEADER_PLATFORM => 'admin',
        ]);
        $this->assertNoException(fn() => PlatformGuard::validate());
    }

    public function test_successful_validation_records_audit_log(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/customer/list',
            PlatformGuard::HEADER_PLATFORM => 'sales',
        ]);
        PlatformGuard::validate();
        $actions = array_column(PlatformGuard::getAuditLog(), 'action');
        $this->assertContains('platform_access', $actions);
    }

    public function test_get_platform_info_returns_correct_structure(): void
    {
        $info = PlatformGuard::getPlatformInfo();
        $this->assertSame('CRM客户跟进系统', $info['name']);
        $this->assertSame('1.0.0', $info['version']);
        $this->assertSame('commercial', $info['type']);
        $this->assertContains('admin', $info['platforms']);
        $this->assertContains('sales', $info['platforms']);
        $this->assertContains('client', $info['platforms']);
        $this->assertContains('admin', array_keys($info['endpoints']));
    }

    public function test_get_platform_labels(): void
    {
        $labels = PlatformGuard::getPlatformLabels();
        $this->assertSame('管理端', $labels['admin']);
        $this->assertSame('销售端', $labels['sales']);
        $this->assertSame('客户端', $labels['client']);
    }

    public function test_boundary_violation_includes_allowed_modules(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/admin/user/list',
            PlatformGuard::HEADER_PLATFORM => 'client',
        ]);
        try {
            PlatformGuard::validate();
        } catch (Exception $e) {
        }
        $violations = PlatformGuard::getViolations();
        $found = false;
        foreach ($violations as $v) {
            if ($v['type'] === 'platform_boundary_violation') {
                $this->assertContains('profile', $v['data']['allowed_modules']);
                $this->assertSame('client', $v['data']['platform']);
                $found = true;
            }
        }
        $this->assertTrue($found);
    }
}
