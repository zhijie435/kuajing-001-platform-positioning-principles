<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CustomerRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CustomerController
{
    public function __construct(
        private CustomerRepository $customerRepo,
    ) {}

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $filters = [
            'search' => $params['search'] ?? '',
            'status' => $params['status'] ?? '',
            'level' => $params['level'] ?? '',
            'source' => $params['source'] ?? '',
        ];
        $page = (int)($params['page'] ?? 1);
        $perPage = (int)($params['per_page'] ?? 20);

        $result = $this->customerRepo->list($filters, $page, $perPage);
        return $this->json($response, true, 'Customers retrieved', $result);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            return $this->json($response, false, 'Customer name is required', null, 400);
        }

        $id = $this->customerRepo->create($data);
        $customer = $this->customerRepo->findById($id);

        return $this->json($response, true, 'Customer created', $customer, 201);
    }

    public function read(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int)($args['id'] ?? 0);
        $customer = $this->customerRepo->findById($id);

        if ($customer === null) {
            return $this->json($response, false, 'Customer not found', null, 404);
        }

        return $this->json($response, true, 'Customer retrieved', $customer);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int)($args['id'] ?? 0);
        $data = $request->getParsedBody();

        $existing = $this->customerRepo->findById($id);
        if ($existing === null) {
            return $this->json($response, false, 'Customer not found', null, 404);
        }

        $result = $this->customerRepo->update($id, $data);
        $customer = $this->customerRepo->findById($id);

        return $this->json($response, true, 'Customer updated', $customer);
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
