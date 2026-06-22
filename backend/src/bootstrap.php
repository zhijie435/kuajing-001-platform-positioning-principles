<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use DI\ContainerBuilder;
use App\Config\Database;

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    'settings' => [
        'displayErrorDetails' => (bool)($_ENV['APP_DEBUG'] ?? true),
        'logErrors' => true,
        'logErrorDetails' => true,
    ],
    'db' => function () {
        return Database::getConnection();
    },
]);

$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(
    (bool)($_ENV['APP_DEBUG'] ?? true),
    true,
    true
);

$app->add(new App\Middleware\CorsMiddleware());

$app->add(new App\Middleware\GuardMiddleware());

(require __DIR__ . '/routes.php')($app);

return $app;
