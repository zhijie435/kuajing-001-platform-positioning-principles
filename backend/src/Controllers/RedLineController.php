<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\RedLineConfig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class RedLineController extends BaseController
{
    public function getConfig(Request $request, Response $response): Response
    {
        $params = $this->getQueryParams($request);
        $platform = $params['platform'] ?? 'all';

        $config = [];
        $defaultConfigs = \App\Guards\RedLineGuard::REDLINE_KEYS;

        foreach ($defaultConfigs as $key => $meta) {
            $config[$key] = [
                'value' => $meta['default'],
                'description' => $meta['description'],
                'type' => $meta['type'],
                'source' => 'default',
            ];
        }

        try {
            $dbConfigs = RedLineConfig::where('platform', $platform)->get();
            foreach ($dbConfigs as $dbConfig) {
                if (isset($config[$dbConfig->config_key])) {
                    $config[$dbConfig->config_key]['value'] = $dbConfig->config_value;
                    $config[$dbConfig->config_key]['source'] = 'database';
                }
            }

            if ($platform !== 'all') {
                $allPlatformConfigs = RedLineConfig::where('platform', 'all')->get();
                foreach ($allPlatformConfigs as $dbConfig) {
                    if (isset($config[$dbConfig->config_key]) && $config[$dbConfig->config_key]['source'] === 'default') {
                        $config[$dbConfig->config_key]['value'] = $dbConfig->config_value;
                        $config[$dbConfig->config_key]['source'] = 'database';
                    }
                }
            }
        } catch (\Exception $e) {
            // 使用默认配置
        }

        return $this->success($response, [
            'platform' => $platform,
            'config' => $config,
        ]);
    }

    public function updateConfig(Request $request, Response $response): Response
    {
        $body = $this->getParsedBody($request);
        $platform = $body['platform'] ?? 'all';
        $configs = $body['configs'] ?? [];

        if (empty($configs) || !is_array($configs)) {
            return $this->error($response, 400, '配置数据格式错误');
        }

        $updated = [];
        $defaultConfigs = \App\Guards\RedLineGuard::REDLINE_KEYS;

        foreach ($configs as $key => $value) {
            if (!isset($defaultConfigs[$key])) {
                continue;
            }

            $meta = $defaultConfigs[$key];
            $typedValue = $value;
            if ($meta['type'] === 'integer') {
                $typedValue = (int)$value;
            } elseif ($meta['type'] === 'boolean') {
                $typedValue = (bool)$value;
            }

            $config = RedLineConfig::where('config_key', $key)
                ->where('platform', $platform)
                ->first();

            if ($config) {
                $config->update([
                    'config_value' => $typedValue,
                ]);
            } else {
                RedLineConfig::create([
                    'config_key' => $key,
                    'config_value' => $typedValue,
                    'description' => $meta['description'],
                    'platform' => $platform,
                ]);
            }

            $updated[$key] = $typedValue;
        }

        AuditController::log(
            'update_redline_config',
            'redline',
            'admin',
            null,
            null,
            'success',
            ['platform' => $platform, 'updated' => $updated]
        );

        return $this->success($response, [
            'platform' => $platform,
            'updated' => $updated,
        ], '红线配置更新成功');
    }
}
