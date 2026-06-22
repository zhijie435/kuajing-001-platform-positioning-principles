<?php

declare(strict_types=1);

use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface;

return function (App $app) {
    $app->get('/api/health', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->group('/api/auth', function (RouteCollectorProxyInterface $group) {
        $group->post('/login', [App\Controllers\AuthController::class, 'login']);
        $group->post('/logout', [App\Controllers\AuthController::class, 'logout']);
    });

    $app->group('/api/admin', function (RouteCollectorProxyInterface $group) {
        $group->get('/audit/list', [App\Controllers\AuditController::class, 'list']);
        $group->get('/audit/detail/{id}', [App\Controllers\AuditController::class, 'detail']);
        
        $group->get('/license/list', [App\Controllers\LicenseController::class, 'list']);
        $group->post('/license/create', [App\Controllers\LicenseController::class, 'create']);
        $group->put('/license/update/{id}', [App\Controllers\LicenseController::class, 'update']);
        $group->delete('/license/delete/{id}', [App\Controllers\LicenseController::class, 'delete']);
        $group->post('/license/activate', [App\Controllers\LicenseController::class, 'activate']);
        
        $group->get('/redline/config', [App\Controllers\RedLineController::class, 'getConfig']);
        $group->put('/redline/config', [App\Controllers\RedLineController::class, 'updateConfig']);
    });

    $app->group('/api/guard', function (RouteCollectorProxyInterface $group) {
        $group->post('/verify', [App\Controllers\GuardController::class, 'verify']);
        $group->get('/info', [App\Controllers\GuardController::class, 'info']);
    });

    $app->group('/api/customer', function (RouteCollectorProxyInterface $group) {
        $group->get('/list', [App\Controllers\CustomerController::class, 'list']);
        $group->get('/detail/{id}', [App\Controllers\CustomerController::class, 'detail']);
        $group->post('/create', [App\Controllers\CustomerController::class, 'create']);
        $group->put('/update/{id}', [App\Controllers\CustomerController::class, 'update']);
    });

    $app->group('/api/follow', function (RouteCollectorProxyInterface $group) {
        $group->get('/list', [App\Controllers\FollowController::class, 'list']);
        $group->post('/create', [App\Controllers\FollowController::class, 'create']);
    });
};
