<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\LicenseService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class LicenseMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LicenseService $licenseService,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        if ($method === 'OPTIONS') {
            return $handler->handle($request);
        }

        if (str_starts_with($path, '/api/auth/') || str_starts_with($path, '/api/license/')) {
            return $handler->handle($request);
        }

        if (!$this->licenseService->isLicenseValid()) {
            $licenseInfo = $this->licenseService->getLicenseInfo();
            $reason = 'License is invalid or expired';
            if ($licenseInfo !== null && $licenseInfo['days_remaining'] <= 0) {
                $reason = 'License has expired';
            }
            return $this->forbidden($reason);
        }

        return $handler->handle($request);
    }

    private function forbidden(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
            'data' => [
                'license_status' => 'invalid',
            ],
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
    }
}
