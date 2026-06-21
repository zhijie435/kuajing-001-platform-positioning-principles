<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'crm_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('PLATFORM_NAME', 'CRM客户跟进系统');
define('PLATFORM_VERSION', '1.0.0');
define('PLATFORM_TYPE', 'commercial');

define('LICENSE_KEY', 'CRM-LICENSE-2026-STD');
define('LICENSE_EXPIRE', '2027-06-21');
define('LICENSE_MAX_USERS', 100);
define('LICENSE_MAX_CLIENTS', 10000);

define('RED_LINE_IP_WHITELIST', ['127.0.0.1', '::1', '192.168.0.0/16', '10.0.0.0/8']);
define('RED_LINE_ACCESS_HOURS', ['start' => '00:00', 'end' => '23:59']);
define('RED_LINE_MAX_REQUESTS_PER_MINUTE', 300);
define('RED_LINE_SESSION_TIMEOUT', 7200);

define('RED_LINE_PLATFORM_CONFIG', [
    'admin' => [
        'enabled' => true,
        'ip_whitelist' => ['127.0.0.1', '::1', '192.168.0.0/16', '10.0.0.0/8'],
        'ip_whitelist_enforce' => true,
        'access_hours' => ['start' => '08:00', 'end' => '22:00'],
        'access_hours_enforce' => false,
        'max_requests_per_minute' => 100,
        'session_timeout' => 1800,
        'require_device_fingerprint' => true,
        'device_fingerprint_threshold' => 0.8,
        'allow_multi_device_login' => false,
        'sensitive_operation_2fa' => true
    ],
    'sales' => [
        'enabled' => true,
        'ip_whitelist' => [],
        'ip_whitelist_enforce' => false,
        'access_hours' => ['start' => '07:00', 'end' => '23:00'],
        'access_hours_enforce' => false,
        'max_requests_per_minute' => 200,
        'session_timeout' => 3600,
        'require_device_fingerprint' => false,
        'device_fingerprint_threshold' => 0.6,
        'allow_multi_device_login' => true,
        'sensitive_operation_2fa' => false
    ],
    'client' => [
        'enabled' => true,
        'ip_whitelist' => [],
        'ip_whitelist_enforce' => false,
        'access_hours' => ['start' => '00:00', 'end' => '23:59'],
        'access_hours_enforce' => false,
        'max_requests_per_minute' => 60,
        'session_timeout' => 86400,
        'require_device_fingerprint' => false,
        'device_fingerprint_threshold' => 0.5,
        'allow_multi_device_login' => true,
        'sensitive_operation_2fa' => true
    ]
]);

define('AUDIT_REQUIRED_OPERATIONS', [
    'customer' => ['create', 'update', 'delete', 'level_upgrade'],
    'opportunity' => ['create', 'update', 'stage_change', 'won', 'lost'],
    'contract' => ['create', 'update', 'sign', 'cancel']
]);

define('AUDIT_STATUS_PENDING', 'pending');
define('AUDIT_STATUS_APPROVED', 'approved');
define('AUDIT_STATUS_REJECTED', 'rejected');
define('AUDIT_STATUS_WRITEBACK_SUCCESS', 'writeback_success');
define('AUDIT_STATUS_WRITEBACK_FAILED', 'writeback_failed');

define('DATA_WRITEBACK_RETRY_MAX', 3);
define('DATA_WRITEBACK_RETRY_INTERVAL', 60);

define('JWT_SECRET', 'crm-system-jwt-secret-key-2026');
define('JWT_EXPIRE', 7200);

define('PLATFORM_ENDPOINTS', [
    'admin' => ['customer', 'user', 'report', 'system', 'license', 'audit', 'redline'],
    'sales' => ['customer', 'followup', 'opportunity', 'contract', 'dashboard', 'audit'],
    'client' => ['profile', 'contract', 'invoice', 'service', 'audit']
]);

define('COMMERCIAL_FEATURE_BOUNDARY', [
    'standard' => ['customer_basic', 'followup_basic', 'dashboard_basic'],
    'professional' => ['customer_advanced', 'followup_advanced', 'opportunity_manage', 'report_basic'],
    'enterprise' => ['customer_full', 'followup_full', 'opportunity_full', 'report_full', 'system_custom', 'api_access']
]);

define('BASE_PATH', dirname(__DIR__));
