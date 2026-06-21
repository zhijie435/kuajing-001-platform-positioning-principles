<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$testSessionPath = sys_get_temp_dir() . '/crm_test_sessions';
if (!is_dir($testSessionPath)) {
    @mkdir($testSessionPath, 0775, true);
}
ini_set('session.save_path', $testSessionPath);
ini_set('session.use_cookies', '0');

$apiRoot = dirname(__DIR__) . '/api';

if (!defined('DB_HOST')) {
    require_once $apiRoot . '/config/config.php';
}

require_once $apiRoot . '/core/LicenseStore.php';
require_once $apiRoot . '/guard/PlatformGuard.php';
require_once $apiRoot . '/guard/CommercialGuard.php';
require_once $apiRoot . '/guard/RedLineGuard.php';

if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'GET';
}
if (!isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = '/api/customer/list';
}
if (!isset($_SERVER['REMOTE_ADDR'])) {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}
if (!isset($_SERVER['HTTP_USER_AGENT'])) {
    $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit-CLI/8.3';
}

require_once __DIR__ . '/Framework/StateReset.php';
require_once __DIR__ . '/Framework/TestCase.php';
require_once __DIR__ . '/Framework/Runner.php';

StateReset::snapshot();
