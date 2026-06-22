<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CustomerRepository;
use App\Repository\FollowUpRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DashboardController
{
    public function __construct(
        private CustomerRepository $customerRepo,
        private FollowUpRepository $followUpRepo,
    ) {}

    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $totalCustomers = $this->customerRepo->totalCount();
        $customersByStatus = $this->customerRepo->countByStatus();
        $customersByLevel = $this->customerRepo->countByLevel();
        $totalFollowUps = $this->followUpRepo->totalCount();
        $followUpsByType = $this->followUpRepo->countByType();
        $todayFollowUps = $this->followUpRepo->countToday();

        $potentialCount = $customersByStatus['potential'] ?? 0;
        $convertedCount = ($customersByStatus['signed'] ?? 0) + ($customersByStatus['deal'] ?? 0);
        $conversionRate = $totalCustomers > 0 ? round(($convertedCount / $totalCustomers) * 100, 2) : 0;

        $data = [
            'customers' => [
                'total' => $totalCustomers,
                'by_status' => $customersByStatus,
                'by_level' => $customersByLevel,
            ],
            'follow_ups' => [
                'total' => $totalFollowUps,
                'today' => $todayFollowUps,
                'by_type' => $followUpsByType,
            ],
            'conversion' => [
                'rate' => $conversionRate,
                'potential' => $potentialCount,
                'converted' => $convertedCount,
            ],
        ];

        return $this->json($response, true, 'Dashboard stats retrieved', $data);
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
