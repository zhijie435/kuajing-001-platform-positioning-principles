<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FollowUpRepository;
use App\Repository\CustomerRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FollowUpController
{
    public function __construct(
        private FollowUpRepository $followUpRepo,
        private CustomerRepository $customerRepo,
    ) {}

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $filters = [
            'customer_id' => $params['customer_id'] ?? '',
            'type' => $params['type'] ?? '',
        ];
        $page = (int)($params['page'] ?? 1);
        $perPage = (int)($params['per_page'] ?? 20);

        $result = $this->followUpRepo->list($filters, $page, $perPage);
        return $this->json($response, true, 'Follow-ups retrieved', $result);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (empty($data['customer_id'])) {
            return $this->json($response, false, 'Customer ID is required', null, 400);
        }

        $customer = $this->customerRepo->findById((int)$data['customer_id']);
        if ($customer === null) {
            return $this->json($response, false, 'Customer not found', null, 404);
        }

        $id = $this->followUpRepo->create($data);
        $followUp = $this->followUpRepo->findById($id);

        return $this->json($response, true, 'Follow-up created', $followUp, 201);
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
