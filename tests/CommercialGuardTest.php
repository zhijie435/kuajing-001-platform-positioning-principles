<?php

declare(strict_types=1);

final class CommercialGuardTest extends TestCase
{
    protected function setUp(): void
    {
        $this->mockRequest([
            'REQUEST_URI' => '/api/customer/basic',
            PlatformGuard::HEADER_PLATFORM => 'admin',
        ]);
        PlatformGuard::validate();
    }

    private function injectLicense(array $license): void
    {
        $ref = new ReflectionProperty(CommercialGuard::class, 'activeLicense');
        $ref->setAccessible(true);
        $ref->setValue(null, $license);
    }

    private function standardLicense(): array
    {
        return [
            'license_key' => 'CRM-LICENSE-2026-STD',
            'edition_code' => 'standard',
            'edition_label' => '标准版',
            'expire' => '2027-06-21',
            'max_users' => 100,
            'max_clients' => 10000,
            'features' => ['customer_basic', 'followup_basic', 'dashboard_basic'],
        ];
    }

    public function test_public_endpoint_bypasses_commercial_guard(): void
    {
        $this->mockRequest(['REQUEST_URI' => '/api/auth/login']);
        $this->assertNoException(fn() => CommercialGuard::validate());
    }

    public function test_valid_standard_license_with_in_boundary_feature_passes(): void
    {
        $this->injectLicense($this->standardLicense());
        $this->setUri('/api/customer/basic');
        $this->assertNoException(fn() => CommercialGuard::validateFeatureBoundary('/api/customer/basic'));
    }

    public function test_dashboard_module_is_bypassed(): void
    {
        $this->injectLicense($this->standardLicense());
        $this->assertNoException(fn() => CommercialGuard::validateFeatureBoundary('/api/dashboard/stats'));
    }

    public function test_admin_module_is_bypassed(): void
    {
        $this->injectLicense($this->standardLicense());
        $this->assertNoException(fn() => CommercialGuard::validateFeatureBoundary('/api/admin/license/info'));
    }

    public function test_feature_out_of_boundary_throws_4105(): void
    {
        $this->injectLicense($this->standardLicense());
        $this->assertErrorCode(4105, fn() => CommercialGuard::validateFeatureBoundary('/api/opportunity/list'));
        $types = array_column(CommercialGuard::getViolations(), 'type');
        $this->assertContains('feature_out_of_boundary', $types);
    }

    public function test_standard_cannot_access_report_feature(): void
    {
        $this->injectLicense($this->standardLicense());
        $this->assertErrorCode(4105, fn() => CommercialGuard::validateFeatureBoundary('/api/report/full'));
    }

    public function test_empty_license_key_throws_4101(): void
    {
        $license = $this->standardLicense();
        $license['license_key'] = '';
        $this->injectLicense($license);
        $this->assertErrorCode(4101, fn() => CommercialGuard::validate());
        $types = array_column(CommercialGuard::getViolations(), 'type');
        $this->assertContains('license_key_empty', $types);
    }

    public function test_invalid_license_signature_throws_4101(): void
    {
        $license = $this->standardLicense();
        $license['license_key'] = 'CRM-LICENSE-FAKE-XXX';
        $this->injectLicense($license);
        $this->assertErrorCode(4101, fn() => CommercialGuard::validate());
        $types = array_column(CommercialGuard::getViolations(), 'type');
        $this->assertContains('invalid_license', $types);
    }

    public function test_expired_license_throws_4102(): void
    {
        $license = $this->standardLicense();
        $license['expire'] = '2020-01-01';
        $this->injectLicense($license);
        $this->assertErrorCode(4102, fn() => CommercialGuard::validate());
        $types = array_column(CommercialGuard::getViolations(), 'type');
        $this->assertContains('license_expired', $types);
    }

    public function test_invalid_expire_format_throws_4109(): void
    {
        $license = $this->standardLicense();
        $license['expire'] = 'not-a-date';
        $this->injectLicense($license);
        $this->assertErrorCode(4109, fn() => CommercialGuard::validate());
    }

    public function test_empty_expire_throws_4109(): void
    {
        $license = $this->standardLicense();
        $license['expire'] = '';
        $this->injectLicense($license);
        $this->assertErrorCode(4109, fn() => CommercialGuard::validate());
    }

    public function test_near_expiry_license_passes_validation(): void
    {
        $license = $this->standardLicense();
        $license['expire'] = date('Y-m-d', strtotime('+15 days'));
        $this->injectLicense($license);
        $this->setUri('/api/customer/basic');
        $this->assertNoException(fn() => CommercialGuard::validate());
    }

    public function test_user_quota_within_limit_passes(): void
    {
        $this->injectLicense($this->standardLicense());
        $this->assertNoException(fn() => CommercialGuard::validateUserQuota(99));
    }

    public function test_user_quota_at_limit_throws_4103(): void
    {
        $this->injectLicense($this->standardLicense());
        $this->assertErrorCode(4103, fn() => CommercialGuard::validateUserQuota(100));
        $types = array_column(CommercialGuard::getViolations(), 'type');
        $this->assertContains('user_limit_exceeded', $types);
    }

    public function test_user_quota_above_limit_throws_4103(): void
    {
        $this->injectLicense($this->standardLicense());
        $this->assertErrorCode(4103, fn() => CommercialGuard::validateUserQuota(150));
    }

    public function test_user_quota_invalid_config_throws_4103(): void
    {
        $license = $this->standardLicense();
        $license['max_users'] = 0;
        $this->injectLicense($license);
        $this->assertErrorCode(4103, fn() => CommercialGuard::validateUserQuota(1));
    }

    public function test_client_quota_within_limit_passes(): void
    {
        $this->injectLicense($this->standardLicense());
        $this->assertNoException(fn() => CommercialGuard::validateClientQuota(9999));
    }

    public function test_client_quota_at_limit_throws_4104(): void
    {
        $this->injectLicense($this->standardLicense());
        $this->assertErrorCode(4104, fn() => CommercialGuard::validateClientQuota(10000));
        $types = array_column(CommercialGuard::getViolations(), 'type');
        $this->assertContains('client_limit_exceeded', $types);
    }

    public function test_client_quota_above_limit_throws_4104(): void
    {
        $this->injectLicense($this->standardLicense());
        $this->assertErrorCode(4104, fn() => CommercialGuard::validateClientQuota(20000));
    }

    public function test_get_current_edition_returns_standard(): void
    {
        $this->injectLicense($this->standardLicense());
        $this->assertSame('standard', CommercialGuard::getCurrentEdition());
    }

    public function test_get_license_info_returns_correct_structure(): void
    {
        $this->injectLicense($this->standardLicense());
        $info = CommercialGuard::getLicenseInfo();
        $this->assertSame('standard', $info['edition_code']);
        $this->assertSame('标准版', $info['edition']);
        $this->assertSame('2027-06-21', $info['expire']);
        $this->assertSame(100, $info['max_users']);
        $this->assertSame(10000, $info['max_clients']);
        $this->assertContains('customer_basic', $info['features']);
        $this->assertTrue($info['days_left'] > 0);
    }

    public function test_license_info_key_is_masked(): void
    {
        $this->injectLicense($this->standardLicense());
        $info = CommercialGuard::getLicenseInfo();
        $this->assertStringEndsWith('***', $info['key']);
    }

    public function test_refresh_active_license_clears_cache(): void
    {
        $this->injectLicense($this->standardLicense());
        $refreshed = CommercialGuard::refreshActiveLicense();
        $this->assertSame('standard', $refreshed['edition_code']);
    }

    public function test_professional_edition_has_more_features(): void
    {
        $license = $this->standardLicense();
        $license['license_key'] = 'CRM-LICENSE-2026-PRO';
        $license['edition_code'] = 'professional';
        $this->injectLicense($license);
        $features = CommercialGuard::getLicenseInfo()['features'];
        $this->assertContains('customer_basic', $features);
        $this->assertContains('opportunity_manage', $features);
        $this->assertContains('report_basic', $features);
    }

    public function test_enterprise_edition_has_all_features(): void
    {
        $license = $this->standardLicense();
        $license['license_key'] = 'CRM-LICENSE-2026-ENT';
        $license['edition_code'] = 'enterprise';
        $this->injectLicense($license);
        $features = CommercialGuard::getLicenseInfo()['features'];
        $this->assertContains('system_custom', $features);
        $this->assertContains('api_access', $features);
        $this->assertContains('report_full', $features);
    }

    public function test_enterprise_can_access_opportunity(): void
    {
        $license = $this->standardLicense();
        $license['license_key'] = 'CRM-LICENSE-2026-ENT';
        $license['edition_code'] = 'enterprise';
        $this->injectLicense($license);
        $this->assertNoException(fn() => CommercialGuard::validateFeatureBoundary('/api/opportunity/full'));
    }

    public function test_violations_are_recorded_for_each_failure(): void
    {
        $this->injectLicense($this->standardLicense());
        try {
            CommercialGuard::validateFeatureBoundary('/api/opportunity/list');
        } catch (Exception $e) {
        }
        $violations = CommercialGuard::getViolations();
        $this->assertGreaterThanOrEqual(1, count($violations));
        $last = end($violations);
        $this->assertSame('feature_out_of_boundary', $last['type']);
        $this->assertSame('opportunity_list', $last['data']['feature']);
    }
}
