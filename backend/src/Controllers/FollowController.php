<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Follow;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FollowController extends BaseController
{
    public function list(Request $request, Response $response): Response
    {
        [$page, $pageSize] = $this->getPageParams($request);
        $params = $this->getQueryParams($request);

        $query = Follow::query();

        if (!empty($params['customer_id'])) {
            $query->where('customer_id', (int)$params['customer_id']);
        }
        if (!empty($params['user_id'])) {
            $query->where('user_id', (int)$params['user_id']);
        }
        if (!empty($params['follow_type'])) {
            $query->where('follow_type', $params['follow_type']);
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

        if (empty($body['customer_id'])) {
            return $this->error($response, 400, '客户ID不能为空');
        }

        $follow = Follow::create([
            'customer_id' => (int)$body['customer_id'],
            'user_id' => !empty($body['user_id']) ? (int)$body['user_id'] : null,
            'follow_type' => $body['follow_type'] ?? 'call',
            'content' => $body['content'] ?? '',
            'next_follow_time' => !empty($body['next_follow_time']) ? $body['next_follow_time'] : null,
            'license_id' => !empty($body['license_id']) ? (int)$body['license_id'] : null,
        ]);

        AuditController::log(
            'create_follow',
            'follow_create',
            $this->getHeader($request, 'X-Platform', 'pc'),
            null,
            null,
            'success',
            $body
        );

        return $this->success($response, $follow->toArray(), '跟进记录创建成功');
    }

    private function getHeader(Request $request, string $name, $default = null)
    {
        $headers = $request->getHeader($name);
        return $headers[0] ?? $default;
    }
}
