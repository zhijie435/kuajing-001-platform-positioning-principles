<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../guard/RedLineGuard.php';

class FollowupController {
    private $mockData = [];

    public function __construct() {
        $this->mockData = $this->getMockFollowups();
    }

    public function list($input) {
        $customerId = (int)($input['customer_id'] ?? 0);
        $type = $input['type'] ?? '';
        $page = max(1, (int)($input['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($input['page_size'] ?? 20)));

        $filtered = $this->mockData;
        if ($customerId > 0) {
            $filtered = array_filter($filtered, fn($f) => $f['customer_id'] === $customerId);
        }
        if ($type) {
            $filtered = array_filter($filtered, fn($f) => $f['type'] === $type);
        }

        usort($filtered, fn($a, $b) => strtotime($b['followup_time']) - strtotime($a['followup_time']));

        $total = count($filtered);
        $offset = ($page - 1) * $pageSize;
        $list = array_slice(array_values($filtered), $offset, $pageSize);

        Response::success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'type_options' => [
                ['value' => 'call', 'label' => '电话沟通'],
                ['value' => 'meeting', 'label' => '上门拜访'],
                ['value' => 'wechat', 'label' => '微信沟通'],
                ['value' => 'email', 'label' => '邮件往来'],
                ['value' => 'sign', 'label' => '合同签约']
            ]
        ]);
    }

    public function create($input) {
        $customerId = (int)($input['customer_id'] ?? 0);
        $type = trim($input['type'] ?? '');
        $content = trim($input['content'] ?? '');

        if (!$customerId || !$type || !$content) {
            Response::badRequest('客户ID、跟进类型和跟进内容不能为空');
        }

        $validTypes = ['call', 'meeting', 'wechat', 'email', 'sign'];
        if (!in_array($type, $validTypes)) {
            Response::badRequest('无效的跟进类型');
        }

        $user = RedLineGuard::getCurrentUser();
        $newId = max(array_column($this->mockData, 'id')) + 1;
        $newFollowup = [
            'id' => $newId,
            'customer_id' => $customerId,
            'customer_name' => $input['customer_name'] ?? '未知客户',
            'type' => $type,
            'type_label' => $this->getTypeLabel($type),
            'content' => $content,
            'followup_time' => $input['followup_time'] ?? date('Y-m-d H:i:s'),
            'next_followup' => $input['next_followup'] ?? null,
            'operator_id' => $user['user_id'] ?? 0,
            'operator_name' => $user['username'] ?? 'system',
            'created_at' => date('Y-m-d H:i:s')
        ];

        Response::success($newFollowup, '跟进记录已保存');
    }

    private function getTypeLabel($type) {
        $labels = ['call' => '电话沟通', 'meeting' => '上门拜访', 'wechat' => '微信沟通', 'email' => '邮件往来', 'sign' => '合同签约'];
        return $labels[$type] ?? $type;
    }

    private function getMockFollowups() {
        return [
            ['id' => 1, 'customer_id' => 1, 'customer_name' => '张伟', 'type' => 'meeting', 'type_label' => '上门拜访', 'content' => '上门洽谈年度合作框架，客户对方案较为满意，预计下周确认合同细节', 'followup_time' => '2026-06-18 14:30:00', 'next_followup' => '2026-06-25 10:00:00', 'operator_id' => 2, 'operator_name' => '张销售', 'created_at' => '2026-06-18 15:00:00'],
            ['id' => 2, 'customer_id' => 2, 'customer_name' => '李娜', 'type' => 'call', 'type_label' => '电话沟通', 'content' => '电话确认报价条款，客户要求增加售后服务模块，需重新核算成本', 'followup_time' => '2026-06-20 10:15:00', 'next_followup' => '2026-06-22 09:30:00', 'operator_id' => 2, 'operator_name' => '张销售', 'created_at' => '2026-06-20 11:00:00'],
            ['id' => 3, 'customer_id' => 4, 'customer_name' => '刘芳', 'type' => 'wechat', 'type_label' => '微信沟通', 'content' => '微信发送产品演示视频，客户反馈将组织内部评审', 'followup_time' => '2026-06-19 17:10:00', 'next_followup' => '2026-06-24 16:00:00', 'operator_id' => 3, 'operator_name' => '李销售', 'created_at' => '2026-06-19 17:30:00'],
            ['id' => 4, 'customer_id' => 1, 'customer_name' => '张伟', 'type' => 'email', 'type_label' => '邮件往来', 'content' => '发送技术白皮书和案例资料', 'followup_time' => '2026-06-10 09:00:00', 'next_followup' => null, 'operator_id' => 2, 'operator_name' => '张销售', 'created_at' => '2026-06-10 09:30:00']
        ];
    }
}
