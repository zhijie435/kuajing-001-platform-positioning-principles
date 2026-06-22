<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Customer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CustomerController extends BaseController
{
    public function list(Request $request, Response $response): Response
    {
        [$page, $pageSize] = $this->getPageParams($request);
        $params = $this->getQueryParams($request);

        $query = Customer::query();

        if (!empty($params['keyword'])) {
            $keyword = '%' . $params['keyword'] . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', $keyword)
                    ->orWhere('phone', 'like', $keyword)
                    ->orWhere('company', 'like', $keyword);
            });
        }
        if (!empty($params['level'])) {
            $query->where('level', $params['level']);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['source'])) {
            $query->where('source', $params['source']);
        }
        if (!empty($params['follow_status'])) {
            $query->where('follow_status', $params['follow_status']);
        }

        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->toArray();

        return $this->paginated($response, $list, $total, $page, $pageSize);
    }

    public function detail(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        $customer = Customer::find($id);
        if (!$customer) {
            return $this->error($response, 404, '客户不存在');
        }

        $data = $customer->toArray();
        try {
            $data['follows'] = $customer->follows()
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            $data['follows'] = [];
        }

        return $this->success($response, $data);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $this->getParsedBody($request);

        $customer = Customer::create([
            'name' => $body['name'] ?? '',
            'phone' => $body['phone'] ?? '',
            'email' => $body['email'] ?? '',
            'company' => $body['company'] ?? '',
            'level' => $body['level'] ?? 'normal',
            'source' => $body['source'] ?? 'manual',
            'status' => $body['status'] ?? 'active',
            'follow_status' => $body['follow_status'] ?? 'pending',
            'assigned_user_id' => !empty($body['assigned_user_id']) ? (int)$body['assigned_user_id'] : null,
            'license_id' => !empty($body['license_id']) ? (int)$body['license_id'] : null,
            'remark' => $body['remark'] ?? '',
        ]);

        AuditController::log(
            'create_customer',
            'customer_create',
            $this->getHeader($request, 'X-Platform', 'pc'),
            null,
            null,
            'success',
            $body
        );

        return $this->success($response, $customer->toArray(), '客户创建成功');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $this->getParsedBody($request);

        $customer = Customer::find($id);
        if (!$customer) {
            return $this->error($response, 404, '客户不存在');
        }

        $updateData = [];
        $allowedFields = ['name', 'phone', 'email', 'company', 'level', 'source', 'status', 'follow_status', 'assigned_user_id', 'next_follow_time', 'remark'];

        foreach ($allowedFields as $field) {
            if (isset($body[$field])) {
                $updateData[$field] = $body[$field];
            }
        }

        if (!empty($updateData)) {
            $customer->update($updateData);
        }

        AuditController::log(
            'update_customer',
            'customer',
            $this->getHeader($request, 'X-Platform', 'pc'),
            null,
            null,
            'success',
            ['id' => $id, 'changes' => $updateData]
        );

        return $this->success($response, $customer->toArray(), '客户更新成功');
    }

    private function getHeader(Request $request, string $name, $default = null)
    {
        $headers = $request->getHeader($name);
        return $headers[0] ?? $default;
    }
}
