<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\LicenseService;
use App\Service\BoundaryService;
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthController
{
    private const JWT_SECRET = 'crm-redline-secret-2024';

    public function __construct(
        private UserRepository $userRepo,
        private LicenseService $licenseService,
        private BoundaryService $boundaryService,
    ) {}

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            return $this->json($response, false, 'Username and password are required', null, 400);
        }

        $user = $this->userRepo->findByUsername($username);
        if ($user === null || !password_verify($password, $user['password'])) {
            return $this->json($response, false, 'Invalid username or password', null, 401);
        }

        $this->userRepo->updateLastLogin((int)$user['id']);

        $payload = [
            'iss' => 'crm-redline',
            'sub' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + 86400,
        ];
        $token = JWT::encode($payload, self::JWT_SECRET, 'HS256');

        $licenseValid = $this->licenseService->isLicenseValid();
        $licenseInfo = $this->licenseService->getLicenseInfo();
        $boundaryResult = $this->boundaryService->checkBoundary($request);

        $redLine = [
            'auth' => true,
            'license' => $licenseValid,
            'license_info' => $licenseInfo,
            'boundary' => $boundaryResult['passed'],
            'boundary_violations' => $boundaryResult['violations'],
        ];

        unset($user['password']);

        return $this->json($response, true, 'Login successful', [
            'token' => $token,
            'user' => $user,
            'redLine' => $redLine,
        ]);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, true, 'Logout successful');
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        return $this->json($response, true, 'User info retrieved', $user);
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
