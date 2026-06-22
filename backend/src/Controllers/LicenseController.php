<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\License;
use App\Models\RedLineConfig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LicenseController extends BaseController
{
    public function list(Request $request, Response $response): Response
    {
        [$page, $pageSize] = $this->getPageParams($request);
        $params = $this->getQueryParams($request);

        $query = License::query();

        if (!empty($params['license_type'])) {
            $query->where('license_type', $params['license_type']);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['keyword'])) {
            $keyword = '%' . $params['keyword'] . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('license_key', 'like', $keyword)
                    ->orWhere('company_name', 'like', $keyword)
                    ->orWhere('contact_email', 'like', $keyword);
            });
        }

        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->toArray();

        return $this->paginated($response, $list, $total, $page, $pageSize);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $this->getParsedBody($request);

        $licenseKey = $this->generateLicenseKey();
        $licenseType = $body['license_type'] ?? 'standard';
        $days = (int)($body['valid_days'] ?? 365);

        $license = License::create([
            'license_key' => $licenseKey,
            'license_type' => $licenseType,
            'platform' => $body['platform'] ?? 'all',
            'max_users' => (int)($body['max_users'] ?? 10),
            'max_customers' => (int)($body['max_customers'] ?? 1000),
            'max_follows_per_day' => (int)($body['max_follows_per_day'] ?? 100),
            'status' => $body['status'] ?? 'inactive',
            'expired_at' => date('Y-m-d H:i:s', strtotime('+' . $days . ' days')),
            'company_name' => $body['company_name'] ?? '',
            'contact_email' => $body['contact_email'] ?? '',
        ]);

        AuditController::log(
            'create_license',
            'license',
            'admin',
            null,
            null,
            'success',
            $body
        );

        return $this->success($response, $license->toArray(), '许可证创建成功');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $this->getParsedBody($request);

        $license = License::find($id);
        if (!$license) {
            return $this->error($response, 404, '许可证不存在');
        }

        $updateData = [];
        $allowedFields = [
            'license_type', 'platform', 'max_users', 'max_customers',
            'max_follows_per_day', 'status', 'expired_at',
            'company_name', 'contact_email',
        ];

        foreach ($allowedFields as $field) {
            if (isset($body[$field])) {
                $updateData[$field] = $body[$field];
            }
        }

        if (!empty($updateData)) {
            $license->update($updateData);
        }

        AuditController::log(
            'update_license',
            'license',
            'admin',
            null,
            null,
            'success',
            ['id' => $id, 'changes' => $updateData]
        );

        return $this->success($response, $license->toArray(), '许可证更新成功');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        $license = License::find($id);
        if (!$license) {
            return $this->error($response, 404, '许可证不存在');
        }

        $license->delete();

        AuditController::log(
            'delete_license',
            'license',
            'admin',
            null,
            null,
            'success',
            ['id' => $id]
        );

        return $this->success($response, [], '许可证删除成功');
    }

    public function activate(Request $request, Response $response): Response
    {
        $body = $this->getParsedBody($request);
        $licenseKey = $body['license_key'] ?? '';

        if (empty($licenseKey)) {
            return $this->error($response, 400, '许可证密钥不能为空');
        }

        $license = License::byKey($licenseKey)->first();
        if (!$license) {
            return $this->error($response, 404, '许可证不存在');
        }

        if ($license->status === 'active') {
            return $this->success($response, $license->toArray(), '许可证已激活');
        }

        if ($license->status !== 'inactive') {
            return $this->error($response, 403, '许可证状态不允许激活');
        }

        $license->update([
            'status' => 'active',
            'activated_at' => date('Y-m-d H:i:s'),
        ]);

        AuditController::log(
            'activate_license',
            'license',
            'pc',
            null,
            null,
            'success',
            ['license_key' => $licenseKey]
        );

        return $this->success($response, $license->toArray(), '许可证激活成功');
    }

    private function generateLicenseKey(): string
    {
        $prefix = 'CRM';
        $random = strtoupper(substr(md5(uniqid() . mt_rand(), true), 0, 16));
        return $prefix . '-' . substr($random, 0, 4) . '-' . substr($random, 4, 4) . '-' . substr($random, 8, 4) . '-' . substr($random, 12, 4);
    }
}
