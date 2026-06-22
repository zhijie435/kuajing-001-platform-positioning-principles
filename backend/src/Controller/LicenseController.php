<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LicenseService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LicenseController
{
    public function __construct(
        private LicenseService $licenseService,
    ) {}

    public function verify(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $info = $this->licenseService->getLicenseInfo();
        if ($info === null) {
            return $this->json($response, false, 'No active license found', null, 404);
        }
        return $this->json($response, true, 'License verified', $info);
    }

    public function activate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $licenseKey = $data['license_key'] ?? '';

        if (empty($licenseKey)) {
            return $this->json($response, false, 'License key is required', null, 400);
        }

        $result = $this->licenseService->activateLicense($licenseKey);
        if (!$result['success']) {
            return $this->json($response, false, $result['message'], null, 400);
        }

        $info = $this->licenseService->getLicenseInfo();
        return $this->json($response, true, $result['message'], $info);
    }

    private function json(ResponseInterface $response, bool $success, string $message, mixed $data = null, int $status = 200): ResponseInterface
    {
        $payload = [
            'success' => $success,
            'message' => $message,
        ];
        if ($data !== null) {
            $payload['data'] = $data;
        }
        $response->getBody()->write(json_encode($payload));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
