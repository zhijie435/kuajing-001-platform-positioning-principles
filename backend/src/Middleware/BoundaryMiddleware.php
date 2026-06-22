<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\BoundaryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class BoundaryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private BoundaryService $boundaryService,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        if ($method === 'OPTIONS') {
            return $handler->handle($request);
        }

        if (str_starts_with($path, '/api/auth/') || str_starts_with($path, '/api/license/') || str_starts_with($path, '/api/boundary/')) {
            return $handler->handle($request);
        }

        $result = $this->boundaryService->checkBoundary($request);

        if (!$result['passed']) {
            return $this->forbidden('Boundary violation detected', $result['violations']);
        }

        return $handler->handle($request);
    }

    private function forbidden(string $message, array $violations): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
            'data' => [
                'violations' => $violations,
            ],
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
    }
}
