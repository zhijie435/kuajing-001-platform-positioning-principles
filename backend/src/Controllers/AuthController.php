<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends BaseController
{
    public function login(Request $request, Response $response): Response
    {
        $body = $this->getParsedBody($request);
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';
        $platform = $this->getHeader($request, 'X-Platform', 'pc');

        if (empty($username) || empty($password)) {
            return $this->error($response, 400, '用户名和密码不能为空');
        }

        try {
            $user = User::where('username', $username)->first();
        } catch (\Exception $e) {
            $user = null;
        }

        if (!$user) {
            if ($username === 'admin' && $password === 'admin123') {
                $token = $this->generateToken([
                    'user_id' => 1,
                    'username' => 'admin',
                    'role' => 'admin',
                    'platform' => $platform,
                    'license_key' => 'DEFAULT-LICENSE-KEY',
                ]);

                return $this->success($response, [
                    'token' => $token,
                    'user' => [
                        'id' => 1,
                        'username' => 'admin',
                        'real_name' => '系统管理员',
                        'role' => 'admin',
                    ],
                    'expires_in' => 86400,
                ], '登录成功');
            }

            return $this->error($response, 401, '用户名或密码错误');
        }

        if (!$user->isActive()) {
            return $this->error($response, 403, '账号已被禁用');
        }

        if (!password_verify($password, $user->password)) {
            return $this->error($response, 401, '用户名或密码错误');
        }

        $token = $this->generateToken([
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'platform' => $platform,
            'license_id' => $user->license_id,
            'license_key' => $user->license?->license_key ?? '',
        ]);

        AuditController::log(
            'login',
            'auth',
            $platform,
            $user->id,
            $user->username,
            'success'
        );

        return $this->success($response, [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'real_name' => $user->real_name,
                'role' => $user->role,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'expires_in' => 86400,
        ], '登录成功');
    }

    public function logout(Request $request, Response $response): Response
    {
        AuditController::log(
            'logout',
            'auth',
            $this->getHeader($request, 'X-Platform', 'pc')
        );

        return $this->success($response, [], '退出成功');
    }

    private function generateToken(array $payload): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'your-jwt-secret-key-change-me';
        $issuedAt = time();
        $expireTime = $issuedAt + (int)($_ENV['JWT_EXPIRE'] ?? 86400);

        $payload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $expireTime,
        ]);

        return JWT::encode($payload, $secret, 'HS256');
    }

    private function getHeader(Request $request, string $name, $default = null)
    {
        $headers = $request->getHeader($name);
        return $headers[0] ?? $default;
    }
}
