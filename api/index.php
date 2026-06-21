<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-Fingerprint, X-Platform-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/guard/PlatformGuard.php';
require_once __DIR__ . '/guard/CommercialGuard.php';
require_once __DIR__ . '/guard/RedLineGuard.php';

try {
    PlatformGuard::validate();
    CommercialGuard::validate();
    RedLineGuard::validate();

    $router = new Router();
    $router->dispatch();
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code >= 4100 && $code < 4200) {
        Response::commercialBlock($code, $e->getMessage(), CommercialGuard::getViolations());
    } else {
        Response::error($code, $e->getMessage());
    }
}
