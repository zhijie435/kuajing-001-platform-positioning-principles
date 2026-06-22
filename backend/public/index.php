<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use App\Config\Database;
use App\Config\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\LicenseMiddleware;
use App\Middleware\BoundaryMiddleware;
use App\Controller\AuthController;
use App\Controller\LicenseController;
use App\Controller\BoundaryController;
use App\Controller\CustomerController;
use App\Controller\FollowUpController;
use App\Controller\DashboardController;
use App\Service\LicenseService;
use App\Service\BoundaryService;
use App\Repository\LicenseRepository;
use App\Repository\BoundaryRuleRepository;
use App\Repository\ViolationLogRepository;
use App\Repository\CustomerRepository;
use App\Repository\FollowUpRepository;
use App\Repository\UserRepository;

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    PDO::class => function () {
        return Database::getConnection();
    },
    UserRepository::class => function ($c) {
        return new UserRepository($c->get(PDO::class));
    },
    LicenseRepository::class => function ($c) {
        return new LicenseRepository($c->get(PDO::class));
    },
    BoundaryRuleRepository::class => function ($c) {
        return new BoundaryRuleRepository($c->get(PDO::class));
    },
    ViolationLogRepository::class => function ($c) {
        return new ViolationLogRepository($c->get(PDO::class));
    },
    CustomerRepository::class => function ($c) {
        return new CustomerRepository($c->get(PDO::class));
    },
    FollowUpRepository::class => function ($c) {
        return new FollowUpRepository($c->get(PDO::class));
    },
    LicenseService::class => function ($c) {
        return new LicenseService($c->get(LicenseRepository::class));
    },
    BoundaryService::class => function ($c) {
        return new BoundaryService(
            $c->get(BoundaryRuleRepository::class),
            $c->get(ViolationLogRepository::class)
        );
    },
    AuthController::class => function ($c) {
        return new AuthController(
            $c->get(UserRepository::class),
            $c->get(LicenseService::class),
            $c->get(BoundaryService::class)
        );
    },
    LicenseController::class => function ($c) {
        return new LicenseController($c->get(LicenseService::class));
    },
    BoundaryController::class => function ($c) {
        return new BoundaryController($c->get(BoundaryService::class));
    },
    CustomerController::class => function ($c) {
        return new CustomerController($c->get(CustomerRepository::class));
    },
    FollowUpController::class => function ($c) {
        return new FollowUpController($c->get(FollowUpRepository::class), $c->get(CustomerRepository::class));
    },
    DashboardController::class => function ($c) {
        return new DashboardController($c->get(CustomerRepository::class), $c->get(FollowUpRepository::class));
    },
]);
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->add(new CorsMiddleware());
$app->addRoutingMiddleware();

$app->add(new BoundaryMiddleware($container->get(BoundaryService::class)));
$app->add(new LicenseMiddleware($container->get(LicenseService::class)));
$app->add(new AuthMiddleware());

$app->post('/api/auth/login', [AuthController::class, 'login']);
$app->post('/api/auth/logout', [AuthController::class, 'logout']);
$app->get('/api/auth/me', [AuthController::class, 'me']);

$app->get('/api/license/verify', [LicenseController::class, 'verify']);
$app->post('/api/license/activate', [LicenseController::class, 'activate']);

$app->post('/api/boundary/check', [BoundaryController::class, 'check']);
$app->get('/api/boundary/rules', [BoundaryController::class, 'rules']);
$app->put('/api/boundary/rules', [BoundaryController::class, 'updateRules']);
$app->get('/api/boundary/violations', [BoundaryController::class, 'violations']);

$app->get('/api/customers', [CustomerController::class, 'list']);
$app->post('/api/customers', [CustomerController::class, 'create']);
$app->get('/api/customers/{id}', [CustomerController::class, 'read']);
$app->put('/api/customers/{id}', [CustomerController::class, 'update']);

$app->get('/api/follow-ups', [FollowUpController::class, 'list']);
$app->post('/api/follow-ups', [FollowUpController::class, 'create']);

$app->get('/api/dashboard/stats', [DashboardController::class, 'stats']);

$app->run();
