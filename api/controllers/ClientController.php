<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../guard/RedLineGuard.php';

class ClientController {
    public function profile($input) {
        $user = RedLineGuard::getCurrentUser();

        Response::success([
            'id' => $user['user_id'] ?? 1001,
            'name' => '王客户',
            'company' => '示例科技有限公司',
            'phone' => '13800138008',
            'email' => 'client@example.com',
            'contact' => '张销售',
            'contact_phone' => '13800138001',
            'level' => 'A级客户',
            'join_date' => '2026-01-15',
            'avatar' => '',
            'company_info' => [
                'industry' => '互联网/科技',
                'scale' => '100-500人',
                'address' => '北京市海淀区中关村大街1号'
            ]
        ]);
    }

    public function contracts($input) {
        $contracts = [
            [
                'id' => 'CT202601001',
                'name' => 'CRM系统年度服务合同',
                'type' => '服务合同',
                'amount' => 280000,
                'status' => 'active',
                'status_label' => '执行中',
                'start_date' => '2026-01-15',
                'end_date' => '2027-01-14',
                'sign_date' => '2026-01-10',
                'owner' => '张销售'
            ],
            [
                'id' => 'CT202606002',
                'name' => '数据迁移服务合同',
                'type' => '技术服务',
                'amount' => 85000,
                'status' => 'won',
                'status_label' => '已完成',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-20',
                'sign_date' => '2026-05-28',
                'owner' => '张销售'
            ],
            [
                'id' => 'CT202607003',
                'name' => '增购用户授权',
                'type' => '产品采购',
                'amount' => 60000,
                'status' => 'pending',
                'status_label' => '待签署',
                'start_date' => '2026-07-01',
                'end_date' => '2027-01-14',
                'sign_date' => null,
                'owner' => '张销售'
            ]
        ];

        Response::success([
            'list' => $contracts,
            'total' => count($contracts),
            'summary' => [
                'total_amount' => array_sum(array_column($contracts, 'amount')),
                'active_count' => count(array_filter($contracts, fn($c) => $c['status'] === 'active')),
                'pending_count' => count(array_filter($contracts, fn($c) => $c['status'] === 'pending'))
            ]
        ]);
    }
}
