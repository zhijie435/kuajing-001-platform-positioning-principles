<?php
require_once __DIR__ . '/../core/Response.php';

class DashboardController {
    public function stats($input) {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        $stats = [
            'overview' => [
                'total_customers' => 156,
                'new_customers_month' => 23,
                'total_opportunities' => 48,
                'total_amount' => 8765000,
                'weighted_amount' => 4123000,
                'won_amount_month' => 85000
            ],
            'today' => [
                'followup_count' => 12,
                'new_leads' => 3,
                'meetings' => 2,
                'calls' => 8
            ],
            'conversion_funnel' => [
                ['stage' => '线索', 'count' => 245, 'rate' => 100],
                ['stage' => '商机', 'count' => 128, 'rate' => 52.2],
                ['stage' => '方案', 'count' => 67, 'rate' => 27.3],
                ['stage' => '谈判', 'count' => 32, 'rate' => 13.1],
                ['stage' => '成交', 'count' => 15, 'rate' => 6.1]
            ],
            'customer_level_dist' => [
                ['level' => 'A', 'label' => 'A级-重点', 'count' => 28, 'color' => '#f5222d'],
                ['level' => 'B', 'label' => 'B级-普通', 'count' => 65, 'color' => '#1890ff'],
                ['level' => 'C', 'label' => 'C级-潜在', 'count' => 63, 'color' => '#52c41a']
            ],
            'sales_ranking' => [
                ['rank' => 1, 'name' => '张销售', 'amount' => 1865000, 'count' => 5],
                ['rank' => 2, 'name' => '李销售', 'amount' => 1128000, 'count' => 3],
                ['rank' => 3, 'name' => '王销售', 'amount' => 856000, 'count' => 2],
                ['rank' => 4, 'name' => '赵销售', 'amount' => 520000, 'count' => 2],
                ['rank' => 5, 'name' => '孙销售', 'amount' => 280000, 'count' => 1]
            ],
            'trend_last_7_days' => $this->generateTrendData()
        ];

        Response::success($stats);
    }

    private function generateTrendData() {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('m-d', strtotime("-$i days"));
            $data[] = [
                'date' => $date,
                'new_customers' => rand(1, 8),
                'followups' => rand(5, 20),
                'opportunities' => rand(0, 5),
                'amount' => rand(50000, 300000)
            ];
        }
        return $data;
    }
}
