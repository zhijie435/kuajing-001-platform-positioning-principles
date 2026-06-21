<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../guard/RedLineGuard.php';
require_once __DIR__ . '/../guard/CommercialGuard.php';

class CustomerController {
    private $mockData = [];

    public function __construct() {
        $this->mockData = $this->getMockCustomers();
    }

    public function list($input) {
        $page = max(1, (int)($input['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($input['page_size'] ?? 10)));
        $keyword = trim($input['keyword'] ?? '');
        $level = $input['level'] ?? '';
        $status = $input['status'] ?? '';

        $filtered = $this->mockData;
        if ($keyword) {
            $filtered = array_filter($filtered, function ($c) use ($keyword) {
                return stripos($c['name'], $keyword) !== false
                    || stripos($c['company'], $keyword) !== false
                    || stripos($c['phone'], $keyword) !== false;
            });
        }
        if ($level) {
            $filtered = array_filter($filtered, fn($c) => $c['level'] === $level);
        }
        if ($status) {
            $filtered = array_filter($filtered, fn($c) => $c['status'] === $status);
        }

        $total = count($filtered);
        $offset = ($page - 1) * $pageSize;
        $list = array_slice(array_values($filtered), $offset, $pageSize);

        Response::success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'quota' => [
                'used' => $total,
                'limit' => LICENSE_MAX_CLIENTS,
                'can_create' => $total < LICENSE_MAX_CLIENTS
            ]
        ]);
    }

    public function detail($input) {
        $id = (int)($input['id'] ?? 0);
        $customer = null;
        foreach ($this->mockData as $c) {
            if ($c['id'] === $id) {
                $customer = $c;
                break;
            }
        }
        if (!$customer) {
            Response::notFound('客户不存在');
        }
        Response::success($customer);
    }

    public function create($input) {
        $name = trim($input['name'] ?? '');
        $company = trim($input['company'] ?? '');
        $phone = trim($input['phone'] ?? '');

        if (!$name || !$company) {
            Response::badRequest('客户名称和公司名称不能为空');
        }

        try {
            CommercialGuard::validateClientQuota(count($this->mockData));
        } catch (Exception $e) {
            Response::forbidden($e->getMessage());
        }

        $user = RedLineGuard::getCurrentUser();
        $newId = max(array_column($this->mockData, 'id')) + 1;
        $newCustomer = [
            'id' => $newId,
            'name' => $name,
            'company' => $company,
            'phone' => $phone,
            'email' => trim($input['email'] ?? ''),
            'level' => $input['level'] ?? 'B',
            'status' => $input['status'] ?? 'potential',
            'source' => $input['source'] ?? 'manual',
            'owner_id' => $user['user_id'] ?? 0,
            'owner_name' => $user['username'] ?? 'system',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        Response::success($newCustomer, '客户创建成功');
    }

    public function update($input) {
        $id = (int)($input['id'] ?? 0);
        $customer = null;
        $idx = -1;
        foreach ($this->mockData as $i => $c) {
            if ($c['id'] === $id) {
                $customer = $c;
                $idx = $i;
                break;
            }
        }
        if (!$customer) {
            Response::notFound('客户不存在');
        }

        $allowedFields = ['name', 'company', 'phone', 'email', 'level', 'status', 'source'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $customer[$field] = $input[$field];
            }
        }
        $customer['updated_at'] = date('Y-m-d H:i:s');

        Response::success($customer, '客户更新成功');
    }

    public function delete($input) {
        $id = (int)($input['id'] ?? 0);
        $exists = false;
        foreach ($this->mockData as $c) {
            if ($c['id'] === $id) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            Response::notFound('客户不存在');
        }

        $user = RedLineGuard::getCurrentUser();
        if ($user && !in_array($user['role'], ['super_admin', 'admin', 'sales_manager'])) {
            Response::forbidden('无权限删除客户');
        }

        Response::success(['id' => $id], '客户已删除');
    }

    private function getMockCustomers() {
        return [
            ['id' => 1, 'name' => '张伟', 'company' => '腾讯科技有限公司', 'phone' => '13800138001', 'email' => 'zhangwei@tencent.com', 'level' => 'A', 'status' => 'active', 'source' => 'referral', 'owner_id' => 2, 'owner_name' => '张销售', 'amount' => 580000, 'followup_count' => 12, 'created_at' => '2026-01-15 09:30:00', 'updated_at' => '2026-06-18 14:20:00'],
            ['id' => 2, 'name' => '李娜', 'company' => '阿里巴巴集团', 'phone' => '13800138002', 'email' => 'lina@alibaba.com', 'level' => 'A', 'status' => 'active', 'source' => 'online', 'owner_id' => 2, 'owner_name' => '张销售', 'amount' => 1200000, 'followup_count' => 28, 'created_at' => '2026-02-20 10:15:00', 'updated_at' => '2026-06-20 11:00:00'],
            ['id' => 3, 'name' => '王强', 'company' => '字节跳动科技', 'phone' => '13800138003', 'email' => 'wangqiang@bytedance.com', 'level' => 'B', 'status' => 'potential', 'source' => 'exhibition', 'owner_id' => 3, 'owner_name' => '李销售', 'amount' => 0, 'followup_count' => 3, 'created_at' => '2026-03-10 14:00:00', 'updated_at' => '2026-06-15 09:45:00'],
            ['id' => 4, 'name' => '刘芳', 'company' => '美团点评', 'phone' => '13800138004', 'email' => 'liufang@meituan.com', 'level' => 'B', 'status' => 'negotiating', 'source' => 'online', 'owner_id' => 3, 'owner_name' => '李销售', 'amount' => 280000, 'followup_count' => 8, 'created_at' => '2026-04-05 16:30:00', 'updated_at' => '2026-06-19 17:10:00'],
            ['id' => 5, 'name' => '陈明', 'company' => '京东商城', 'phone' => '13800138005', 'email' => 'chenming@jd.com', 'level' => 'C', 'status' => 'potential', 'source' => 'cold_call', 'owner_id' => 2, 'owner_name' => '张销售', 'amount' => 0, 'followup_count' => 1, 'created_at' => '2026-05-12 11:20:00', 'updated_at' => '2026-05-12 11:20:00']
        ];
    }
}
