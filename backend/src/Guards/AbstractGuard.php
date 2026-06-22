<?php

declare(strict_types=1);

namespace App\Guards;

use Psr\Http\Message\ServerRequestInterface as Request;

abstract class AbstractGuard implements GuardInterface
{
    protected string $name = 'abstract';
    protected int $priority = 100;

    abstract public function verify(Request $request): GuardResult;

    public function getName(): string
    {
        return $this->name;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    protected function getHeader(Request $request, string $name, $default = null)
    {
        $headers = $request->getHeader($name);
        return $headers[0] ?? $default;
    }

    protected function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}
