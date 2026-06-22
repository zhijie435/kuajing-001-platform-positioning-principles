<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Guards\GuardChain;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

class GuardMiddleware
{
    private GuardChain $guardChain;

    public function __construct(?GuardChain $guardChain = null)
    {
        $this->guardChain = $guardChain ?? GuardChain::createDefault();
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $chainResult = $this->guardChain->verifyAll($request);

        $request = $request->withAttribute('guard_results', $chainResult->getAllResults());
        $request = $request->withAttribute('guard_passed', $chainResult->isAllPassed());

        $response = $handler->handle($request);

        $guardResultJson = json_encode($chainResult->toArray(), JSON_UNESCAPED_UNICODE);
        $response = $response->withHeader('X-Guard-Result', base64_encode($guardResultJson));

        return $response;
    }
}
