<?php

declare(strict_types=1);

namespace App\Guards;

use Psr\Http\Message\ServerRequestInterface as Request;

interface GuardInterface
{
    public function verify(Request $request): GuardResult;

    public function getName(): string;

    public function getPriority(): int;
}
