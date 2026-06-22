<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;

$licenseCount = Capsule::table('licenses')->count();
if ($licenseCount === 0) {
    Capsule::table('licenses')->insert([
        [
            'license_key' => 'CRM-DEMO-LICENSE-KEY-001',
            'license_type' => 'professional',
            'platform' => 'all',
            'max_users' => 50,
            'max_customers' => 10000,
            'max_follows_per_day' => 500,
            'status' => 'active',
            'expired_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'activated_at' => date('Y-m-d H:i:s'),
            'company_name' => '演示公司',
            'contact_email' => 'demo@example.com',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ],
        [
            'license_key' => 'CRM-TRIAL-LICENSE-KEY-002',
            'license_type' => 'trial',
            'platform' => 'pc,mobile',
            'max_users' => 5,
            'max_customers' => 100,
            'max_follows_per_day' => 20,
            'status' => 'active',
            'expired_at' => date('Y-m-d H:i:s', strtotime('+15 days')),
            'activated_at' => date('Y-m-d H:i:s'),
            'company_name' => '试用公司',
            'contact_email' => 'trial@example.com',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ],
    ]);
    echo "✓ 许可证种子数据已导入\n";
}

$userCount = Capsule::table('users')->count();
if ($userCount === 0) {
    Capsule::table('users')->insert([
        [
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'real_name' => '系统管理员',
            'email' => 'admin@example.com',
            'phone' => '13800138000',
            'role' => 'admin',
            'status' => 'active',
            'license_id' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ],
        [
            'username' => 'sales01',
            'password' => password_hash('sales123', PASSWORD_DEFAULT),
            'real_name' => '销售小王',
            'email' => 'sales01@example.com',
            'phone' => '13800138001',
            'role' => 'sales',
            'status' => 'active',
            'license_id' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ],
    ]);
    echo "✓ 用户种子数据已导入\n";
}

$configCount = Capsule::table('redline_configs')->count();
if ($configCount === 0) {
    $configs = [
        ['config_key' => 'daily_api_limit', 'config_value' => '1000', 'description' => '每日API调用次数限制', 'platform' => 'all'],
        ['config_key' => 'daily_follow_limit', 'config_value' => '100', 'description' => '每日跟进记录数量限制', 'platform' => 'all'],
        ['config_key' => 'daily_customer_create_limit', 'config_value' => '50', 'description' => '每日新增客户数量限制', 'platform' => 'all'],
        ['config_key' => 'bulk_operation_max_size', 'config_value' => '100', 'description' => '批量操作最大数量限制', 'platform' => 'all'],
        ['config_key' => 'sensitive_module_enabled', 'config_value' => '1', 'description' => '是否启用敏感操作校验', 'platform' => 'all'],
        ['config_key' => 'risk_detection_enabled', 'config_value' => '1', 'description' => '是否启用风险检测', 'platform' => 'all'],
        ['config_key' => 'abnormal_behavior_detection', 'config_value' => '1', 'description' => '是否启用异常行为检测', 'platform' => 'all'],
        ['config_key' => 'request_rate_per_minute', 'config_value' => '60', 'description' => '每分钟请求速率限制', 'platform' => 'all'],
    ];

    foreach ($configs as $config) {
        $config['created_at'] = date('Y-m-d H:i:s');
        $config['updated_at'] = date('Y-m-d H:i:s');
        Capsule::table('redline_configs')->insert($config);
    }
    echo "✓ 红线配置种子数据已导入\n";
}

$customerCount = Capsule::table('customers')->count();
if ($customerCount === 0) {
    $customers = [
        ['name' => '张三', 'phone' => '13900139001', 'email' => 'zhangsan@example.com', 'company' => '阿里巴巴', 'level' => 'important', 'source' => 'manual', 'status' => 'active', 'follow_status' => 'following'],
        ['name' => '李四', 'phone' => '13900139002', 'email' => 'lisi@example.com', 'company' => '腾讯科技', 'level' => 'normal', 'source' => 'miniapp', 'status' => 'active', 'follow_status' => 'pending'],
        ['name' => '王五', 'phone' => '13900139003', 'email' => 'wangwu@example.com', 'company' => '字节跳动', 'level' => 'potential', 'source' => 'referral', 'status' => 'active', 'follow_status' => 'pending'],
        ['name' => '赵六', 'phone' => '13900139004', 'email' => 'zhaoliu@example.com', 'company' => '百度公司', 'level' => 'normal', 'source' => 'import', 'status' => 'active', 'follow_status' => 'following'],
        ['name' => '钱七', 'phone' => '13900139005', 'email' => 'qianqi@example.com', 'company' => '美团点评', 'level' => 'important', 'source' => 'manual', 'status' => 'active', 'follow_status' => 'closed'],
    ];

    foreach ($customers as $customer) {
        $customer['license_id'] = 1;
        $customer['assigned_user_id'] = 2;
        $customer['created_at'] = date('Y-m-d H:i:s');
        $customer['updated_at'] = date('Y-m-d H:i:s');
        Capsule::table('customers')->insert($customer);
    }
    echo "✓ 客户种子数据已导入\n";
}
