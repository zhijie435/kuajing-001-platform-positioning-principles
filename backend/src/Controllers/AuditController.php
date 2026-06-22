<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuditController extends BaseController
{
    public function list(Request $request, Response $response): Response
    {
        [$page, $pageSize] = $this->getPageParams($request);
        $params = $this->getQueryParams($request);

        $query = AuditLog::query();

        if (!empty($params['platform'])) {
            $query->where('platform', $params['platform']);
        }
        if (!empty($params['module'])) {
            $query->where('module', $params['module']);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['username'])) {
            $query->where('username', 'like', '%' . $params['username'] . '%');
        }
        if (!empty($params['start_date'])) {
            $query->where('created_at', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->where('created_at', '<=', $params['end_date'] . ' 23:59:59');
        }
        if (!empty($params['guard_result']) && $params['guard_result'] !== 'all') {
            if ($params['guard_result'] === 'passed') {
                $query->where('guard_result', 'passed');
            } else {
                $query->where('guard_result', '!=', 'passed');
            }
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

        $record = AuditLog::find($id);
        if (!$record) {
            return $this->error($response, 404, '审计记录不存在');
        }

        return $this->success($response, $record->toArray());
    }

    public static function log(
        string $action,
        string $module,
        string $platform,
        ?int $userId = null,
        ?string $username = null,
        string $status = 'success',
        array $params = [],
        string $guardResult = 'passed',
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        try {
            AuditLog::create([
                'user_id' => $userId,
                'username' => $username,
                'action' => $action,
                'module' => $module,
                'platform' => $platform,
                'ip' => $ip ?? (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown'),
                'user_agent' => $userAgent ?? (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''),
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'request_path' => $_SERVER['REQUEST_URI'] ?? '',
                'request_params' => $params,
                'response_code' => 0,
                'guard_result' => $guardResult,
                'status' => $status,
                'remark' => '',
            ]);
        } catch (\Exception $e) {
            // 审计日志失败不影响主流程
        }
    }
}
