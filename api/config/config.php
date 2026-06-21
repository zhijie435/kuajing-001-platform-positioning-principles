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

define('JWT_SECRET', 'crm-system-jwt-secret-key-2026');
define('JWT_EXPIRE', 7200);

define('PLATFORM_ENDPOINTS', [
    'admin' => ['customer', 'user', 'report', 'system', 'license', 'audit'],
    'sales' => ['customer', 'followup', 'opportunity', 'contract', 'dashboard'],
    'client' => ['profile', 'contract', 'invoice', 'service']
]);

define('COMMERCIAL_FEATURE_BOUNDARY', [
    'standard' => ['customer_basic', 'followup_basic', 'dashboard_basic'],
    'professional' => ['customer_advanced', 'followup_advanced', 'opportunity_manage', 'report_basic'],
    'enterprise' => ['customer_full', 'followup_full', 'opportunity_full', 'report_full', 'system_custom', 'api_access']
]);

define('BASE_PATH', dirname(__DIR__));
