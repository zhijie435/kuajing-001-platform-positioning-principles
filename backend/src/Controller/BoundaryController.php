<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BoundaryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class BoundaryController
{
    public function __construct(
        private BoundaryService $boundaryService,
    ) {}

    public function check(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $result = $this->boundaryService->checkBoundary($request);
        return $this->json($response, true, 'Boundary check completed', $result);
    }

    public function rules(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = $this->boundaryService->getRules();
        return $this->json($response, true, 'Boundary rules retrieved', $rules);
    }

    public function updateRules(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (empty($data) || !isset($data['rules']) || !is_array($data['rules'])) {
            return $this->json($response, false, 'Rules data is required', null, 400);
        }

        $updated = [];
        foreach ($data['rules'] as $rule) {
            if (!isset($rule['id'])) {
                continue;
            }
            $result = $this->boundaryService->updateRule((int)$rule['id'], $rule);
            if ($result) {
                $updated[] = $rule['id'];
            }
        }

        return $this->json($response, true, 'Rules updated', ['updated_ids' => $updated]);
    }

    public function violations(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page = (int)($params['page'] ?? 1);
        $perPage = (int)($params['per_page'] ?? 20);

        $result = $this->boundaryService->getViolations($page, $perPage);
        return $this->json($response, true, 'Violations retrieved', $result);
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
