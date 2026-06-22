<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware implements MiddlewareInterface
{
    private const JWT_SECRET = 'crm-redline-secret-2024';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        if ($method === 'OPTIONS') {
            return $handler->handle($request);
        }

        if (str_starts_with($path, '/api/auth/login')) {
            return $handler->handle($request);
        }

        $header = $request->getHeaderLine('Authorization');
        if (empty($header) || !str_starts_with($header, 'Bearer ')) {
            return $this->unauthorized('Missing or invalid Authorization header');
        }

        $token = substr($header, 7);
        try {
            $decoded = JWT::decode($token, new Key(self::JWT_SECRET, 'HS256'));
            $request = $request->withAttribute('user', (array)$decoded);
        } catch (\Exception $e) {
            return $this->unauthorized('Invalid or expired token: ' . $e->getMessage());
        }

        return $handler->handle($request);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
