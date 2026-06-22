<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Guards\GuardChain;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class GuardController extends BaseController
{
    public function verify(Request $request, Response $response): Response
    {
        $guardChain = GuardChain::createDefault();
        $result = $guardChain->verifyAll($request);

        return $this->success($response, $result->toArray());
    }

    public function info(Request $request, Response $response): Response
    {
        $guardChain = GuardChain::createDefault();
        $guards = [];

        foreach ($guardChain->getGuards() as $guard) {
            $guards[] = [
                'name' => $guard->getName(),
                'priority' => $guard->getPriority(),
                'class' => get_class($guard),
            ];
        }

        $platformCodes = [
            '4001' => '缺少平台标识',
            '4002' => '不支持的平台类型',
            '4003' => '平台签名验证失败',
            '4004' => '请求时间戳已过期',
        ];

        $commercialCodes = [
            '4101' => '缺少许可证信息',
            '4102' => '许可证无效',
            '4103' => '许可证已过期',
            '4104' => '许可证已被停用',
            '4105' => '用户数量超出许可证限制',
            '4106' => '客户数量超出许可证限制',
            '4107' => '当前许可证不支持该功能',
            '4108' => 'API调用次数超出限制',
        ];

        $redlineCodes = [
            '4201' => '每日API调用次数已达上限',
            '4202' => '敏感操作被拦截',
            '4203' => '数据量超出限制',
            '4204' => '风险用户被拦截',
            '4205' => '异常行为被检测',
            '4206' => '批量操作超出限制',
        ];

        return $this->success($response, [
            'guards' => $guards,
            'error_codes' => [
                'platform' => $platformCodes,
                'commercial' => $commercialCodes,
                'redline' => $redlineCodes,
            ],
            'platforms' => ['pc', 'mobile', 'admin', 'miniapp'],
            'license_types' => ['trial', 'standard', 'professional', 'enterprise'],
        ]);
    }
}
