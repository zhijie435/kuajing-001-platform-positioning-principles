<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../guard/RedLineGuard.php';
require_once __DIR__ . '/../guard/PlatformGuard.php';

class AuditController {
    private $mockAuditRecords = [];
    private $mockCustomers = [];

    public function __construct() {
        $this->mockAuditRecords = $this->getMockAuditRecords();
        $this->mockCustomers = $this->getMockCustomers();
    }

    private function getPermissionFilter() {
        $user = RedLineGuard::getCurrentUser();
        $currentPlatform = PlatformGuard::getCurrentPlatform();
        $userId = $user['user_id'] ?? 0;
        $userRole = $user['role'] ?? '';

        return [
            'is_admin' => $currentPlatform === 'admin' && in_array($userRole, ['super_admin', 'admin', 'sales_manager']),
            'current_platform' => $currentPlatform,
            'user_id' => $userId,
            'user_role' => $userRole
        ];
    }

    private function applyPermissionFilter($records) {
        $filter = $this->getPermissionFilter();

        if (!$filter['is_admin']) {
            $records = array_filter($records, function($r) use ($filter) {
                return $r['submitter_id'] == $filter['user_id'];
            });
        }

        return $records;
    }

    private function checkRecordPermission($record) {
        $filter = $this->getPermissionFilter();

        if ($filter['is_admin']) {
            return true;
        }

        return $record && $record['submitter_id'] == $filter['user_id'];
    }

    public function list($input) {
        $page = max(1, (int)($input['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($input['page_size'] ?? 10)));
        $status = $input['status'] ?? '';
        $targetType = $input['target_type'] ?? '';
        $platform = $input['platform'] ?? '';
        $keyword = trim($input['keyword'] ?? '');

        $filtered = $this->applyPermissionFilter($this->mockAuditRecords);

        if ($status) {
            $filtered = array_filter($filtered, fn($r) => $r['status'] === $status);
        }
        if ($targetType) {
            $filtered = array_filter($filtered, fn($r) => $r['target_type'] === $targetType);
        }
        if ($platform) {
            $filtered = array_filter($filtered, fn($r) => $r['submitter_platform'] === $platform);
        }
        if ($keyword) {
            $filtered = array_filter($filtered, function($r) use ($keyword) {
                return stripos($r['audit_no'], $keyword) !== false
                    || stripos($r['change_summary'], $keyword) !== false
                    || stripos($r['submitter_name'], $keyword) !== false;
            });
        }

        usort($filtered, fn($a, $b) => strtotime($b['submitted_at']) - strtotime($a['submitted_at']));

        $total = count($filtered);
        $offset = ($page - 1) * $pageSize;
        $list = array_slice(array_values($filtered), $offset, $pageSize);

        $stats = $this->getAuditStats();

        Response::success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'stats' => $stats,
            'filter' => $this->getPermissionFilter(),
            'status_options' => [
                ['value' => 'pending', 'label' => '待审核'],
                ['value' => 'approved', 'label' => '已通过'],
                ['value' => 'rejected', 'label' => '已驳回'],
                ['value' => 'writeback_success', 'label' => '回写成功'],
                ['value' => 'writeback_failed', 'label' => '回写失败']
            ],
            'target_type_options' => [
                ['value' => 'customer', 'label' => '客户'],
                ['value' => 'opportunity', 'label' => '商机'],
                ['value' => 'contract', 'label' => '合同']
            ],
            'platform_options' => [
                ['value' => 'admin', 'label' => '管理端'],
                ['value' => 'sales', 'label' => '销售端'],
                ['value' => 'client', 'label' => '客户端']
            ]
        ]);
    }

    public function detail($input) {
        $id = (int)($input['id'] ?? 0);
        $auditNo = trim($input['audit_no'] ?? '');

        $record = null;
        foreach ($this->mockAuditRecords as $r) {
            if ($r['id'] === $id || $r['audit_no'] === $auditNo) {
                $record = $r;
                break;
            }
        }

        if (!$record) {
            Response::notFound('审核记录不存在');
        }

        if (!$this->checkRecordPermission($record)) {
            Response::forbidden('无权限查看该审核记录');
        }

        $logs = $this->getMockAuditLogs($record['id']);

        Response::success([
            'record' => $record,
            'logs' => $logs,
            'can_approve' => $this->canApprove(),
            'can_reject' => $this->canApprove(),
            'can_retry_writeback' => $this->canApprove() && $record['status'] === 'writeback_failed'
        ]);
    }

    public function submit($input) {
        $targetType = trim($input['target_type'] ?? '');
        $targetId = (int)($input['target_id'] ?? 0);
        $operationType = trim($input['operation_type'] ?? '');
        $dataBefore = $input['data_before'] ?? null;
        $dataAfter = $input['data_after'] ?? null;
        $changeSummary = trim($input['change_summary'] ?? '');
        $remark = trim($input['remark'] ?? '');

        if (!$targetType || !$operationType) {
            Response::badRequest('目标类型和操作类型不能为空');
        }

        $requiredOps = AUDIT_REQUIRED_OPERATIONS;
        $typeOps = $requiredOps[$targetType] ?? [];
        if (!in_array($operationType, $typeOps)) {
            Response::success([
                'need_audit' => false,
                'message' => '该操作无需审核，直接执行'
            ], '无需审核，操作已执行');
        }

        $user = RedLineGuard::getCurrentUser();
        $platform = PlatformGuard::getCurrentPlatform();

        $newId = max(array_column($this->mockAuditRecords, 'id')) + 1;
        $auditNo = 'AUD' . date('Ymd') . str_pad($newId, 4, '0', STR_PAD_LEFT);

        $operationLabels = [
            'create' => '新增',
            'update' => '修改',
            'delete' => '删除',
            'level_upgrade' => '等级升级',
            'stage_change' => '阶段变更',
            'won' => '赢单',
            'lost' => '输单',
            'sign' => '签署',
            'cancel' => '取消'
        ];

        $newRecord = [
            'id' => $newId,
            'audit_no' => $auditNo,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'operation_type' => $operationType,
            'operation_label' => $operationLabels[$operationType] ?? $operationType,
            'data_before' => $dataBefore,
            'data_after' => $dataAfter,
            'change_summary' => $changeSummary,
            'status' => AUDIT_STATUS_PENDING,
            'submitter_id' => $user['user_id'] ?? 0,
            'submitter_name' => $user['username'] ?? 'unknown',
            'submitter_platform' => $platform,
            'submitted_at' => date('Y-m-d H:i:s'),
            'auditor_id' => 0,
            'auditor_name' => '',
            'audit_remark' => '',
            'audited_at' => null,
            'writeback_attempts' => 0,
            'writeback_error' => '',
            'writeback_at' => null,
            'remark' => $remark
        ];

        $this->mockAuditRecords[] = $newRecord;

        Response::success([
            'need_audit' => true,
            'audit_id' => $newId,
            'audit_no' => $auditNo,
            'status' => AUDIT_STATUS_PENDING,
            'message' => '已提交审核，请等待管理员审核'
        ], '已提交审核');
    }

    public function approve($input) {
        if (!$this->canApprove()) {
            Response::forbidden('无审核权限');
        }

        $id = (int)($input['id'] ?? 0);
        $remark = trim($input['remark'] ?? '');

        $record = null;
        $idx = -1;
        foreach ($this->mockAuditRecords as $i => $r) {
            if ($r['id'] === $id) {
                $record = $r;
                $idx = $i;
                break;
            }
        }

        if (!$record) {
            Response::notFound('审核记录不存在');
        }

        if ($record['status'] !== AUDIT_STATUS_PENDING && $record['status'] !== AUDIT_STATUS_WRITEBACK_FAILED) {
            Response::badRequest('当前状态不允许审核通过');
        }

        $user = RedLineGuard::getCurrentUser();
        $platform = PlatformGuard::getCurrentPlatform();

        $record['status'] = AUDIT_STATUS_APPROVED;
        $record['auditor_id'] = $user['user_id'] ?? 0;
        $record['auditor_name'] = $user['username'] ?? 'admin';
        $record['audit_remark'] = $remark;
        $record['audited_at'] = date('Y-m-d H:i:s');

        $writebackResult = $this->executeWriteback($record);

        if ($writebackResult['success']) {
            $record['status'] = AUDIT_STATUS_WRITEBACK_SUCCESS;
            $record['writeback_attempts'] = $record['writeback_attempts'] + 1;
            $record['writeback_at'] = date('Y-m-d H:i:s');
            $record['writeback_error'] = '';
        } else {
            $record['status'] = AUDIT_STATUS_WRITEBACK_FAILED;
            $record['writeback_attempts'] = $record['writeback_attempts'] + 1;
            $record['writeback_error'] = $writebackResult['error'] ?? '回写失败';
        }

        $this->mockAuditRecords[$idx] = $record;

        Response::success([
            'id' => $id,
            'status' => $record['status'],
            'writeback_success' => $writebackResult['success'],
            'writeback_result' => $writebackResult,
            'message' => $writebackResult['success'] ? '审核通过，数据回写成功' : '审核通过，但数据回写失败'
        ], $writebackResult['success'] ? '审核通过，数据已回写' : '审核通过，数据回写失败');
    }

    public function reject($input) {
        if (!$this->canApprove()) {
            Response::forbidden('无审核权限');
        }

        $id = (int)($input['id'] ?? 0);
        $remark = trim($input['remark'] ?? '');

        if (!$remark) {
            Response::badRequest('驳回意见不能为空');
        }

        $record = null;
        $idx = -1;
        foreach ($this->mockAuditRecords as $i => $r) {
            if ($r['id'] === $id) {
                $record = $r;
                $idx = $i;
                break;
            }
        }

        if (!$record) {
            Response::notFound('审核记录不存在');
        }

        if ($record['status'] !== AUDIT_STATUS_PENDING) {
            Response::badRequest('当前状态不允许驳回');
        }

        $user = RedLineGuard::getCurrentUser();

        $record['status'] = AUDIT_STATUS_REJECTED;
        $record['auditor_id'] = $user['user_id'] ?? 0;
        $record['auditor_name'] = $user['username'] ?? 'admin';
        $record['audit_remark'] = $remark;
        $record['audited_at'] = date('Y-m-d H:i:s');

        $this->mockAuditRecords[$idx] = $record;

        Response::success([
            'id' => $id,
            'status' => AUDIT_STATUS_REJECTED
        ], '已驳回');
    }

    public function retryWriteback($input) {
        if (!$this->canApprove()) {
            Response::forbidden('无权限操作');
        }

        $id = (int)($input['id'] ?? 0);

        $record = null;
        $idx = -1;
        foreach ($this->mockAuditRecords as $i => $r) {
            if ($r['id'] === $id) {
                $record = $r;
                $idx = $i;
                break;
            }
        }

        if (!$record) {
            Response::notFound('审核记录不存在');
        }

        if ($record['status'] !== AUDIT_STATUS_WRITEBACK_FAILED) {
            Response::badRequest('只有回写失败的记录才能重试');
        }

        if ($record['writeback_attempts'] >= DATA_WRITEBACK_RETRY_MAX) {
            Response::badRequest(sprintf('回写重试次数已达上限（%d次上限，请联系技术支持', DATA_WRITEBACK_RETRY_MAX));
        }

        $writebackResult = $this->executeWriteback($record);

        if ($writebackResult['success']) {
            $record['status'] = AUDIT_STATUS_WRITEBACK_SUCCESS;
            $record['writeback_attempts'] = $record['writeback_attempts'] + 1;
            $record['writeback_at'] = date('Y-m-d H:i:s');
            $record['writeback_error'] = '';
        } else {
            $record['writeback_attempts'] = $record['writeback_attempts'] + 1;
            $record['writeback_error'] = $writebackResult['error'] ?? '回写失败';
        }

        $this->mockAuditRecords[$idx] = $record;

        Response::success([
            'id' => $id,
            'status' => $record['status'],
            'writeback_success' => $writebackResult['success'],
            'writeback_attempts' => $record['writeback_attempts'],
            'writeback_result' => $writebackResult
        ], $writebackResult['success'] ? '数据回写成功' : '数据回写失败');
    }

    public function myPending($input) {
        $user = RedLineGuard::getCurrentUser();
        $userId = $user['user_id'] ?? 0;

        $pending = array_filter($this->mockAuditRecords, function($r) use ($userId) {
            return $r['submitter_id'] == $userId && $r['status'] === AUDIT_STATUS_PENDING;
        });

        Response::success([
            'count' => count($pending),
            'list' => array_slice(array_values($pending), 0, 5)
        ]);
    }

    public function stats($input) {
        $stats = $this->getAuditStats();
        Response::success($stats);
    }

    private function executeWriteback($record) {
        $targetType = $record['target_type'];
        $operationType = $record['operation_type'];
        $dataAfter = is_array($record['data_after']) ? $record['data_after'] : json_decode($record['data_after'], true);
        $dataBefore = is_array($record['data_before']) ? $record['data_before'] : json_decode($record['data_before'], true);
        $targetId = $record['target_id'];

        try {
            switch ($targetType) {
                case 'customer':
                    return $this->writebackCustomer($operationType, $targetId, $dataAfter, $dataBefore);
                case 'opportunity':
                    return $this->writebackOpportunity($operationType, $targetId, $dataAfter, $dataBefore);
                case 'contract':
                    return $this->writebackContract($operationType, $targetId, $dataAfter, $dataBefore);
                default:
                    return ['success' => false, 'error' => '未知的目标类型: ' . $targetType];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function writebackCustomer($operationType, $targetId, $dataAfter, $dataBefore) {
        switch ($operationType) {
            case 'create':
                $newId = max(array_column($this->mockCustomers, 'id')) + 1;
                $newCustomer = array_merge([
                    'id' => $newId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ], $dataAfter);
                $this->mockCustomers[] = $newCustomer;
                return ['success' => true, 'target_id' => $newId, 'data' => $newCustomer];

            case 'update':
            case 'level_upgrade':
                foreach ($this->mockCustomers as $i => $c) {
                    if ($c['id'] == $targetId) {
                        $this->mockCustomers[$i] = array_merge($c, $dataAfter, [
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        return ['success' => true, 'target_id' => $targetId, 'data' => $this->mockCustomers[$i]];
                    }
                }
                return ['success' => false, 'error' => '客户不存在，ID: ' . $targetId];

            case 'delete':
                foreach ($this->mockCustomers as $i => $c) {
                    if ($c['id'] == $targetId) {
                        $this->mockCustomers[$i]['status'] = 'deleted';
                        $this->mockCustomers[$i]['updated_at'] = date('Y-m-d H:i:s');
                        return ['success' => true, 'target_id' => $targetId];
                    }
                }
                return ['success' => false, 'error' => '客户不存在，ID: ' . $targetId];

            default:
                return ['success' => false, 'error' => '未知的操作类型: ' . $operationType];
        }
    }

    private function writebackOpportunity($operationType, $targetId, $dataAfter, $dataBefore) {
        return ['success' => true, 'target_id' => $targetId, 'message' => '商机数据回写成功（模拟）'];
    }

    private function writebackContract($operationType, $targetId, $dataAfter, $dataBefore) {
        return ['success' => true, 'target_id' => $targetId, 'message' => '合同数据回写成功（模拟）'];
    }

    private function canApprove() {
        $user = RedLineGuard::getCurrentUser();
        $role = $user['role'] ?? '';
        return in_array($role, ['super_admin', 'admin', 'sales_manager']);
    }

    private function getAuditStats() {
        $filteredRecords = $this->applyPermissionFilter($this->mockAuditRecords);

        $total = count($filteredRecords);
        $pending = count(array_filter($filteredRecords, fn($r) => $r['status'] === AUDIT_STATUS_PENDING));
        $approved = count(array_filter($filteredRecords, fn($r) => $r['status'] === AUDIT_STATUS_APPROVED));
        $rejected = count(array_filter($filteredRecords, fn($r) => $r['status'] === AUDIT_STATUS_REJECTED));
        $writebackSuccess = count(array_filter($filteredRecords, fn($r) => $r['status'] === AUDIT_STATUS_WRITEBACK_SUCCESS));
        $writebackFailed = count(array_filter($filteredRecords, fn($r) => $r['status'] === AUDIT_STATUS_WRITEBACK_FAILED));

        $today = date('Y-m-d');
        $todayCount = count(array_filter($filteredRecords, function($r) use ($today) {
            return strpos($r['submitted_at'], $today) === 0;
        }));

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'writeback_success' => $writebackSuccess,
            'writeback_failed' => $writebackFailed,
            'today_submitted' => $todayCount
        ];
    }

    private function getMockAuditRecords() {
        return [
            [
                'id' => 1,
                'audit_no' => 'AUD202606220001',
                'target_type' => 'customer',
                'target_id' => 3,
                'operation_type' => 'level_upgrade',
                'operation_label' => '客户等级升级',
                'data_before' => json_encode(['level' => 'C', 'name' => '王强']),
                'data_after' => json_encode(['level' => 'B', 'name' => '王强']),
                'change_summary' => '客户等级从C升级到B',
                'status' => 'pending',
                'submitter_id' => 3,
                'submitter_name' => '李销售',
                'submitter_platform' => 'sales',
                'submitted_at' => '2026-06-22 10:30:00',
                'auditor_id' => 0,
                'auditor_name' => '',
                'audit_remark' => '',
                'audited_at' => null,
                'writeback_attempts' => 0,
                'writeback_error' => '',
                'writeback_at' => null,
                'remark' => '客户近期意向强烈，建议提升等级便于跟进'
            ],
            [
                'id' => 2,
                'audit_no' => 'AUD202606210002',
                'target_type' => 'customer',
                'target_id' => 6,
                'operation_type' => 'create',
                'operation_label' => '新增客户',
                'data_before' => json_encode(new stdClass()),
                'data_after' => json_encode(['name' => '赵六', 'company' => '小米科技', 'phone' => '13800138006', 'level' => 'B']),
                'change_summary' => '新增客户：赵六-小米科技',
                'status' => 'writeback_success',
                'submitter_id' => 2,
                'submitter_name' => '张销售',
                'submitter_platform' => 'sales',
                'submitted_at' => '2026-06-21 14:00:00',
                'auditor_id' => 1,
                'auditor_name' => '系统管理员',
                'audit_remark' => '信息完整，同意新增',
                'audited_at' => '2026-06-21 15:30:00',
                'writeback_attempts' => 1,
                'writeback_error' => '',
                'writeback_at' => '2026-06-21 15:30:05',
                'remark' => '展会收集的名片'
            ],
            [
                'id' => 3,
                'audit_no' => 'AUD202606200003',
                'target_type' => 'customer',
                'target_id' => 4,
                'operation_type' => 'update',
                'operation_label' => '修改客户',
                'data_before' => json_encode(['level' => 'B', 'status' => 'potential']),
                'data_after' => json_encode(['level' => 'A', 'status' => 'active']),
                'change_summary' => '客户等级从B升级到A，状态从潜在变为活跃',
                'status' => 'rejected',
                'submitter_id' => 3,
                'submitter_name' => '李销售',
                'submitter_platform' => 'sales',
                'submitted_at' => '2026-06-20 09:15:00',
                'auditor_id' => 1,
                'auditor_name' => '系统管理员',
                'audit_remark' => '当前成交金额不足，暂不支持升级到A级',
                'audited_at' => '2026-06-20 11:00:00',
                'writeback_attempts' => 0,
                'writeback_error' => '',
                'writeback_at' => null,
                'remark' => ''
            ],
            [
                'id' => 4,
                'audit_no' => 'AUD202606190004',
                'target_type' => 'opportunity',
                'target_id' => 2,
                'operation_type' => 'stage_change',
                'operation_label' => '商机阶段变更',
                'data_before' => json_encode(['stage' => 'proposal', 'probability' => 50]),
                'data_after' => json_encode(['stage' => 'negotiation', 'probability' => 75]),
                'change_summary' => '商机阶段从方案提交进入商务谈判',
                'status' => 'writeback_failed',
                'submitter_id' => 2,
                'submitter_name' => '张销售',
                'submitter_platform' => 'sales',
                'submitted_at' => '2026-06-19 16:45:00',
                'auditor_id' => 1,
                'auditor_name' => '系统管理员',
                'audit_remark' => '同意推进',
                'audited_at' => '2026-06-19 17:00:00',
                'writeback_attempts' => 2,
                'writeback_error' => '数据库连接超时，请重试',
                'writeback_at' => null,
                'remark' => '大项目，重点跟进中'
            ]
        ];
    }

    private function getMockAuditLogs($auditId) {
        $logs = [
            1 => [
                [
                    'id' => 101,
                    'audit_id' => 1,
                    'action' => 'submit',
                    'action_label' => '提交审核',
                    'operator_id' => 3,
                    'operator_name' => '李销售',
                    'operator_platform' => 'sales',
                    'remark' => '提交客户等级升级申请',
                    'created_at' => '2026-06-22 10:30:00'
                ]
            ],
            2 => [
                [
                    'id' => 201,
                    'audit_id' => 2,
                    'action' => 'submit',
                    'action_label' => '提交审核',
                    'operator_id' => 2,
                    'operator_name' => '张销售',
                    'operator_platform' => 'sales',
                    'remark' => '新增客户提交审核',
                    'created_at' => '2026-06-21 14:00:00'
                ],
                [
                    'id' => 202,
                    'audit_id' => 2,
                    'action' => 'approve',
                    'action_label' => '审核通过',
                    'operator_id' => 1,
                    'operator_name' => '系统管理员',
                    'operator_platform' => 'admin',
                    'remark' => '信息完整，同意新增',
                    'created_at' => '2026-06-21 15:30:00'
                ],
                [
                    'id' => 203,
                    'audit_id' => 2,
                    'action' => 'writeback_success',
                    'action_label' => '数据回写成功',
                    'operator_id' => 0,
                    'operator_name' => 'system',
                    'operator_platform' => 'system',
                    'remark' => '数据已成功写入客户表',
                    'created_at' => '2026-06-21 15:30:05'
                ]
            ],
            3 => [
                [
                    'id' => 301,
                    'audit_id' => 3,
                    'action' => 'submit',
                    'action_label' => '提交审核',
                    'operator_id' => 3,
                    'operator_name' => '李销售',
                    'operator_platform' => 'sales',
                    'remark' => '提交客户信息修改',
                    'created_at' => '2026-06-20 09:15:00'
                ],
                [
                    'id' => 302,
                    'audit_id' => 3,
                    'action' => 'reject',
                    'action_label' => '审核驳回',
                    'operator_id' => 1,
                    'operator_name' => '系统管理员',
                    'operator_platform' => 'admin',
                    'remark' => '当前成交金额不足，暂不支持升级到A级',
                    'created_at' => '2026-06-20 11:00:00'
                ]
            ],
            4 => [
                [
                    'id' => 401,
                    'audit_id' => 4,
                    'action' => 'submit',
                    'action_label' => '提交审核',
                    'operator_id' => 2,
                    'operator_name' => '张销售',
                    'operator_platform' => 'sales',
                    'remark' => '提交商机阶段变更',
                    'created_at' => '2026-06-19 16:45:00'
                ],
                [
                    'id' => 402,
                    'audit_id' => 4,
                    'action' => 'approve',
                    'action_label' => '审核通过',
                    'operator_id' => 1,
                    'operator_name' => '系统管理员',
                    'operator_platform' => 'admin',
                    'remark' => '同意推进',
                    'created_at' => '2026-06-19 17:00:00'
                ],
                [
                    'id' => 403,
                    'audit_id' => 4,
                    'action' => 'writeback_failed',
                    'action_label' => '数据回写失败',
                    'operator_id' => 0,
                    'operator_name' => 'system',
                    'operator_platform' => 'system',
                    'remark' => '数据库连接超时，请重试',
                    'created_at' => '2026-06-19 17:00:10'
                ]
            ]
        ];
        return $logs[$auditId] ?? [];
    }

    private function getMockCustomers() {
        return [
            ['id' => 1, 'name' => '张伟', 'company' => '腾讯科技有限公司', 'phone' => '13800138001', 'level' => 'A', 'status' => 'active'],
            ['id' => 2, 'name' => '李娜', 'company' => '阿里巴巴集团', 'phone' => '13800138002', 'level' => 'A', 'status' => 'active'],
            ['id' => 3, 'name' => '王强', 'company' => '字节跳动科技', 'phone' => '13800138003', 'level' => 'C', 'status' => 'potential'],
            ['id' => 4, 'name' => '刘芳', 'company' => '美团点评', 'phone' => '13800138004', 'level' => 'B', 'status' => 'negotiating'],
            ['id' => 5, 'name' => '陈明', 'company' => '京东商城', 'phone' => '13800138005', 'level' => 'C', 'status' => 'potential']
        ];
    }
}
