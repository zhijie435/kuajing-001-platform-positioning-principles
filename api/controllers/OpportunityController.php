<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../guard/RedLineGuard.php';

class OpportunityController {
    private $mockData = [];

    public function __construct() {
        $this->mockData = $this->getMockOpportunities();
    }

    public function list($input) {
        $stage = $input['stage'] ?? '';
        $page = max(1, (int)($input['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($input['page_size'] ?? 20)));

        $filtered = $this->mockData;
        if ($stage) {
            $filtered = array_filter($filtered, fn($o) => $o['stage'] === $stage);
        }

        usort($filtered, fn($a, $b) => $b['amount'] - $a['amount']);

        $total = count($filtered);
        $offset = ($page - 1) * $pageSize;
        $list = array_slice(array_values($filtered), $offset, $pageSize);

        Response::success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'stage_summary' => $this->getStageSummary(),
            'total_amount' => array_sum(array_column($this->mockData, 'amount')),
            'weighted_amount' => array_sum(array_map(function ($o) {
                return $o['amount'] * ($o['probability'] / 100);
            }, $this->mockData))
        ]);
    }

    public function create($input) {
        $customerId = (int)($input['customer_id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $amount = (float)($input['amount'] ?? 0);

        if (!$customerId || !$name || $amount <= 0) {
            Response::badRequest('客户ID、商机名称和预估金额不能为空');
        }

        $user = RedLineGuard::getCurrentUser();
        $newId = max(array_column($this->mockData, 'id')) + 1;
        $newOpportunity = [
            'id' => $newId,
            'customer_id' => $customerId,
            'customer_name' => $input['customer_name'] ?? '未知客户',
            'name' => $name,
            'amount' => $amount,
            'stage' => $input['stage'] ?? 'initial',
            'stage_label' => $this->getStageLabel($input['stage'] ?? 'initial'),
            'probability' => (int)($input['probability'] ?? 10),
            'expected_close' => $input['expected_close'] ?? date('Y-m-d', strtotime('+30 days')),
            'description' => $input['description'] ?? '',
            'owner_id' => $user['user_id'] ?? 0,
            'owner_name' => $user['username'] ?? 'system',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        Response::success($newOpportunity, '商机创建成功');
    }

    public function update($input) {
        $id = (int)($input['id'] ?? 0);
        $opp = null;
        foreach ($this->mockData as $o) {
            if ($o['id'] === $id) {
                $opp = $o;
                break;
            }
        }
        if (!$opp) {
            Response::notFound('商机不存在');
        }

        $allowedFields = ['name', 'amount', 'stage', 'probability', 'expected_close', 'description'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $opp[$field] = $input[$field];
            }
        }
        if (isset($input['stage'])) {
            $opp['stage_label'] = $this->getStageLabel($input['stage']);
        }
        $opp['updated_at'] = date('Y-m-d H:i:s');

        Response::success($opp, '商机更新成功');
    }

    private function getStageLabel($stage) {
        $labels = [
            'initial' => '初步接触',
            'qualified' => '需求确认',
            'proposal' => '方案提交',
            'negotiation' => '商务谈判',
            'won' => '赢单',
            'lost' => '输单'
        ];
        return $labels[$stage] ?? $stage;
    }

    private function getStageSummary() {
        $summary = [];
        $stages = ['initial' => '初步接触', 'qualified' => '需求确认', 'proposal' => '方案提交', 'negotiation' => '商务谈判', 'won' => '赢单', 'lost' => '输单'];
        foreach ($stages as $key => $label) {
            $items = array_filter($this->mockData, fn($o) => $o['stage'] === $key);
            $summary[] = [
                'stage' => $key,
                'label' => $label,
                'count' => count($items),
                'amount' => array_sum(array_column($items, 'amount'))
            ];
        }
        return $summary;
    }

    private function getMockOpportunities() {
        return [
            ['id' => 1, 'customer_id' => 2, 'customer_name' => '李娜', 'name' => '2026年度CRM系统采购项目', 'amount' => 1200000, 'stage' => 'negotiation', 'stage_label' => '商务谈判', 'probability' => 75, 'expected_close' => '2026-07-15', 'description' => '客户计划Q3完成采购，涉及50个用户授权', 'owner_id' => 2, 'owner_name' => '张销售', 'created_at' => '2026-04-01 09:00:00', 'updated_at' => '2026-06-20 10:00:00'],
            ['id' => 2, 'customer_id' => 1, 'customer_name' => '张伟', 'name' => 'SaaS平台定制化开发', 'amount' => 580000, 'stage' => 'proposal', 'stage_label' => '方案提交', 'probability' => 50, 'expected_close' => '2026-08-01', 'description' => '已提交初步方案，等待客户技术评审', 'owner_id' => 2, 'owner_name' => '张销售', 'created_at' => '2026-05-10 14:00:00', 'updated_at' => '2026-06-18 15:00:00'],
            ['id' => 3, 'customer_id' => 4, 'customer_name' => '刘芳', 'name' => '企业版License采购', 'amount' => 280000, 'stage' => 'qualified', 'stage_label' => '需求确认', 'probability' => 35, 'expected_close' => '2026-07-30', 'description' => '客户需求已基本确认，准备报价', 'owner_id' => 3, 'owner_name' => '李销售', 'created_at' => '2026-06-01 11:00:00', 'updated_at' => '2026-06-19 17:00:00'],
            ['id' => 4, 'customer_id' => 2, 'customer_name' => '李娜', 'name' => '历史数据迁移服务', 'amount' => 85000, 'stage' => 'won', 'stage_label' => '赢单', 'probability' => 100, 'expected_close' => '2026-06-10', 'description' => '合同已签署', 'owner_id' => 2, 'owner_name' => '张销售', 'created_at' => '2026-03-15 09:00:00', 'updated_at' => '2026-06-10 16:00:00']
        ];
    }
}
