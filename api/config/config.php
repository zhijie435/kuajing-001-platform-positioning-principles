<?php

function env_get($key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function env_get_array($key, $default = []) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    $parts = array_map('trim', explode(',', $value));
    return array_filter($parts, function ($v) { return $v !== ''; });
}

function env_get_bool($key, $default = false) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
}

function env_get_int($key, $default = 0) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return (int)$value;
}

function env_get_float($key, $default = 0.0) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return (float)$value;
}

define('DB_HOST', env_get('DB_HOST', '127.0.0.1'));
define('DB_PORT', env_get_int('DB_PORT', 3306));
define('DB_NAME', env_get('DB_NAME', 'crm_system'));
define('DB_USER', env_get('DB_USER', 'root'));
define('DB_PASS', env_get('DB_PASS', ''));
define('DB_CHARSET', env_get('DB_CHARSET', 'utf8mb4'));

define('PLATFORM_NAME', env_get('PLATFORM_NAME', 'CRM客户跟进系统'));
define('PLATFORM_VERSION', env_get('PLATFORM_VERSION', '1.0.0'));
define('PLATFORM_TYPE', env_get('PLATFORM_TYPE', 'commercial'));

define('LICENSE_KEY', env_get('LICENSE_KEY', 'CRM-LICENSE-2026-STD'));
define('LICENSE_EXPIRE', env_get('LICENSE_EXPIRE', '2027-06-21'));
define('LICENSE_MAX_USERS', env_get_int('LICENSE_MAX_USERS', 100));
define('LICENSE_MAX_CLIENTS', env_get_int('LICENSE_MAX_CLIENTS', 10000));

define('RED_LINE_IP_WHITELIST', env_get_array('RED_LINE_IP_WHITELIST', ['127.0.0.1', '::1', '192.168.0.0/16', '10.0.0.0/8']));
define('RED_LINE_ACCESS_HOURS', [
    'start' => env_get('RED_LINE_ACCESS_HOURS_START', '00:00'),
    'end' => env_get('RED_LINE_ACCESS_HOURS_END', '23:59')
]);
define('RED_LINE_MAX_REQUESTS_PER_MINUTE', env_get_int('RED_LINE_MAX_REQUESTS_PER_MINUTE', 300));
define('RED_LINE_SESSION_TIMEOUT', env_get_int('RED_LINE_SESSION_TIMEOUT', 7200));

define('RED_LINE_PLATFORM_CONFIG', [
    'admin' => [
        'enabled' => env_get_bool('ADMIN_REDLINE_ENABLED', true),
        'ip_whitelist' => env_get_array('ADMIN_IP_WHITELIST', ['127.0.0.1', '::1', '192.168.0.0/16', '10.0.0.0/8']),
        'ip_whitelist_enforce' => env_get_bool('ADMIN_IP_WHITELIST_ENFORCE', true),
        'access_hours' => [
            'start' => env_get('ADMIN_ACCESS_HOURS_START', '08:00'),
            'end' => env_get('ADMIN_ACCESS_HOURS_END', '22:00')
        ],
        'access_hours_enforce' => env_get_bool('ADMIN_ACCESS_HOURS_ENFORCE', false),
        'max_requests_per_minute' => env_get_int('ADMIN_MAX_REQUESTS_PER_MINUTE', 100),
        'session_timeout' => env_get_int('ADMIN_SESSION_TIMEOUT', 1800),
        'require_device_fingerprint' => env_get_bool('ADMIN_REQUIRE_DEVICE_FP', true),
        'device_fingerprint_threshold' => env_get_float('ADMIN_DEVICE_FP_THRESHOLD', 0.8),
        'allow_multi_device_login' => env_get_bool('ADMIN_ALLOW_MULTI_DEVICE', false),
        'sensitive_operation_2fa' => env_get_bool('ADMIN_SENSITIVE_2FA', true)
    ],
    'sales' => [
        'enabled' => env_get_bool('SALES_REDLINE_ENABLED', true),
        'ip_whitelist' => env_get_array('SALES_IP_WHITELIST', []),
        'ip_whitelist_enforce' => env_get_bool('SALES_IP_WHITELIST_ENFORCE', false),
        'access_hours' => [
            'start' => env_get('SALES_ACCESS_HOURS_START', '07:00'),
            'end' => env_get('SALES_ACCESS_HOURS_END', '23:00')
        ],
        'access_hours_enforce' => env_get_bool('SALES_ACCESS_HOURS_ENFORCE', false),
        'max_requests_per_minute' => env_get_int('SALES_MAX_REQUESTS_PER_MINUTE', 200),
        'session_timeout' => env_get_int('SALES_SESSION_TIMEOUT', 3600),
        'require_device_fingerprint' => env_get_bool('SALES_REQUIRE_DEVICE_FP', false),
        'device_fingerprint_threshold' => env_get_float('SALES_DEVICE_FP_THRESHOLD', 0.6),
        'allow_multi_device_login' => env_get_bool('SALES_ALLOW_MULTI_DEVICE', true),
        'sensitive_operation_2fa' => env_get_bool('SALES_SENSITIVE_2FA', false)
    ],
    'client' => [
        'enabled' => env_get_bool('CLIENT_REDLINE_ENABLED', true),
        'ip_whitelist' => env_get_array('CLIENT_IP_WHITELIST', []),
        'ip_whitelist_enforce' => env_get_bool('CLIENT_IP_WHITELIST_ENFORCE', false),
        'access_hours' => [
            'start' => env_get('CLIENT_ACCESS_HOURS_START', '00:00'),
            'end' => env_get('CLIENT_ACCESS_HOURS_END', '23:59')
        ],
        'access_hours_enforce' => env_get_bool('CLIENT_ACCESS_HOURS_ENFORCE', false),
        'max_requests_per_minute' => env_get_int('CLIENT_MAX_REQUESTS_PER_MINUTE', 60),
        'session_timeout' => env_get_int('CLIENT_SESSION_TIMEOUT', 86400),
        'require_device_fingerprint' => env_get_bool('CLIENT_REQUIRE_DEVICE_FP', false),
        'device_fingerprint_threshold' => env_get_float('CLIENT_DEVICE_FP_THRESHOLD', 0.5),
        'allow_multi_device_login' => env_get_bool('CLIENT_ALLOW_MULTI_DEVICE', true),
        'sensitive_operation_2fa' => env_get_bool('CLIENT_SENSITIVE_2FA', true)
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

define('DATA_WRITEBACK_RETRY_MAX', env_get_int('DATA_WRITEBACK_RETRY_MAX', 3));
define('DATA_WRITEBACK_RETRY_INTERVAL', env_get_int('DATA_WRITEBACK_RETRY_INTERVAL', 60));

define('JWT_SECRET', env_get('JWT_SECRET', 'crm-system-jwt-secret-key-2026'));
define('JWT_EXPIRE', env_get_int('JWT_EXPIRE', 7200));

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
