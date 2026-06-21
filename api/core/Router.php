<?php
class Router {
    private $routes = [];

    public function __construct() {
        $this->registerRoutes();
    }

    private function registerRoutes() {
        $this->routes = [
            'GET' => [
                'auth/platform-info' => ['AuthController', 'platformInfo'],
                'auth/check' => ['AuthController', 'check'],
                'customer/list' => ['CustomerController', 'list'],
                'customer/detail' => ['CustomerController', 'detail'],
                'followup/list' => ['FollowupController', 'list'],
                'opportunity/list' => ['OpportunityController', 'list'],
                'dashboard/stats' => ['DashboardController', 'stats'],
                'admin/user/list' => ['AdminController', 'userList'],
                'admin/license/info' => ['AdminController', 'licenseInfo'],
                'admin/license/list' => ['AdminController', 'licenseList'],
                'admin/license/detail' => ['AdminController', 'licenseDetail'],
                'admin/audit/logs' => ['AdminController', 'auditLogs'],
                'client/profile' => ['ClientController', 'profile'],
                'client/contracts' => ['ClientController', 'contracts']
            ],
            'POST' => [
                'auth/login' => ['AuthController', 'login'],
                'auth/logout' => ['AuthController', 'logout'],
                'auth/refresh' => ['AuthController', 'refresh'],
                'customer/create' => ['CustomerController', 'create'],
                'customer/update' => ['CustomerController', 'update'],
                'customer/delete' => ['CustomerController', 'delete'],
                'followup/create' => ['FollowupController', 'create'],
                'opportunity/create' => ['OpportunityController', 'create'],
                'opportunity/update' => ['OpportunityController', 'update'],
                'admin/user/create' => ['AdminController', 'userCreate'],
                'admin/license/verify' => ['AdminController', 'licenseVerify'],
                'admin/license/activate' => ['AdminController', 'licenseActivate']
            ],
            'PUT' => [
                'customer/update' => ['CustomerController', 'update'],
                'opportunity/update' => ['OpportunityController', 'update']
            ],
            'DELETE' => [
                'customer/delete' => ['CustomerController', 'delete']
            ]
        ];
    }

    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = trim($uri, '/');
        $uri = preg_replace('#^api/?#', '', $uri);

        if (!isset($this->routes[$method])) {
            Response::error(405, '请求方法不允许');
        }

        if (!isset($this->routes[$method][$uri])) {
            Response::notFound('接口不存在: ' . $uri);
        }

        [$controllerName, $actionName] = $this->routes[$method][$uri];

        $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';
        if (!file_exists($controllerFile)) {
            Response::error(500, '控制器不存在: ' . $controllerName);
        }

        require_once $controllerFile;

        if (!class_exists($controllerName)) {
            Response::error(500, '控制器类未定义: ' . $controllerName);
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $actionName)) {
            Response::error(500, '方法不存在: ' . $actionName);
        }

        $input = $this->getInputData();
        call_user_func([$controller, $actionName], $input);
    }

    private function getInputData() {
        $data = array_merge($_GET, $_POST);
        $rawInput = file_get_contents('php://input');
        if ($rawInput) {
            $jsonData = json_decode($rawInput, true);
            if (is_array($jsonData)) {
                $data = array_merge($data, $jsonData);
            }
        }
        return $data;
    }
}
