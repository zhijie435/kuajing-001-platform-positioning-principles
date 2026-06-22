<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class BaseController
{
    protected function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    protected function success(Response $response, array $data = [], string $message = 'success'): Response
    {
        return $this->jsonResponse($response, [
            'code' => 0,
            'message' => $message,
            'data' => $data,
        ]);
    }

    protected function error(Response $response, int $code, string $message, array $data = []): Response
    {
        $statusCode = $this->getHttpStatusCode($code);
        return $this->jsonResponse($response, [
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    protected function paginated(Response $response, array $list, int $total, int $page, int $pageSize): Response
    {
        return $this->success($response, [
            'list' => $list,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'total_pages' => (int)ceil($total / $pageSize),
            ],
        ]);
    }

    protected function getQueryParams(Request $request): array
    {
        return $request->getQueryParams();
    }

    protected function getParsedBody(Request $request): array
    {
        return (array)$request->getParsedBody();
    }

    protected function getPageParams(Request $request): array
    {
        $params = $this->getQueryParams($request);
        $page = (int)($params['page'] ?? 1);
        $pageSize = (int)($params['page_size'] ?? 20);
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        return [$page, $pageSize];
    }

    private function getHttpStatusCode(int $code): int
    {
        if ($code === 0) {
            return 200;
        }
        if ($code >= 4000 && $code < 5000) {
            return 403;
        }
        if ($code >= 400 && $code < 600) {
            return $code;
        }
        return 400;
    }
}
