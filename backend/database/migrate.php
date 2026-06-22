<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv->load();
}

$capsule = new Capsule();

$driver = $_ENV['DB_DRIVER'] ?? 'sqlite';
if ($driver === 'sqlite') {
    $dbPath = __DIR__ . '/database.sqlite';
    if (!file_exists($dbPath)) {
        touch($dbPath);
    }
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => $dbPath,
        'prefix' => '',
    ]);
} else {
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? 'crm_redline',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ]);
}

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "正在创建数据表...\n";

if (!Capsule::schema()->hasTable('licenses')) {
    Capsule::schema()->create('licenses', function ($table) {
        $table->increments('id');
        $table->string('license_key', 100)->unique();
        $table->enum('license_type', ['trial', 'standard', 'professional', 'enterprise'])->default('standard');
        $table->string('platform', 50)->default('all');
        $table->integer('max_users')->default(10);
        $table->integer('max_customers')->default(1000);
        $table->integer('max_follows_per_day')->default(100);
        $table->enum('status', ['inactive', 'active', 'suspended', 'expired'])->default('inactive');
        $table->timestamp('expired_at')->nullable();
        $table->timestamp('activated_at')->nullable();
        $table->string('company_name', 200)->default('');
        $table->string('contact_email', 100)->default('');
        $table->timestamps();
        $table->index('license_key');
        $table->index('status');
    });
    echo "✓ licenses 表创建成功\n";
}

if (!Capsule::schema()->hasTable('users')) {
    Capsule::schema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('username', 50)->unique();
        $table->string('password', 255);
        $table->string('real_name', 50)->default('');
        $table->string('email', 100)->default('');
        $table->string('phone', 20)->default('');
        $table->enum('role', ['admin', 'manager', 'sales'])->default('sales');
        $table->enum('status', ['active', 'inactive'])->default('active');
        $table->integer('license_id')->nullable();
        $table->timestamps();
        $table->index('username');
        $table->index('license_id');
    });
    echo "✓ users 表创建成功\n";
}

if (!Capsule::schema()->hasTable('customers')) {
    Capsule::schema()->create('customers', function ($table) {
        $table->increments('id');
        $table->string('name', 100);
        $table->string('phone', 20)->default('');
        $table->string('email', 100)->default('');
        $table->string('company', 200)->default('');
        $table->enum('level', ['important', 'normal', 'potential'])->default('normal');
        $table->enum('source', ['manual', 'import', 'miniapp', 'referral'])->default('manual');
        $table->enum('status', ['active', 'inactive'])->default('active');
        $table->enum('follow_status', ['pending', 'following', 'closed'])->default('pending');
        $table->timestamp('next_follow_time')->nullable();
        $table->integer('assigned_user_id')->nullable();
        $table->integer('license_id')->nullable();
        $table->text('remark')->nullable();
        $table->timestamps();
        $table->index('name');
        $table->index('phone');
        $table->index('assigned_user_id');
        $table->index('license_id');
    });
    echo "✓ customers 表创建成功\n";
}

if (!Capsule::schema()->hasTable('follows')) {
    Capsule::schema()->create('follows', function ($table) {
        $table->increments('id');
        $table->integer('customer_id');
        $table->integer('user_id')->nullable();
        $table->enum('follow_type', ['call', 'meeting', 'email', 'wechat', 'other'])->default('call');
        $table->text('content')->nullable();
        $table->timestamp('next_follow_time')->nullable();
        $table->integer('license_id')->nullable();
        $table->timestamps();
        $table->index('customer_id');
        $table->index('user_id');
        $table->index('created_at');
    });
    echo "✓ follows 表创建成功\n";
}

if (!Capsule::schema()->hasTable('redline_configs')) {
    Capsule::schema()->create('redline_configs', function ($table) {
        $table->increments('id');
        $table->string('config_key', 100);
        $table->text('config_value');
        $table->string('description', 255)->default('');
        $table->string('platform', 50)->default('all');
        $table->timestamps();
        $table->unique(['config_key', 'platform']);
        $table->index('platform');
    });
    echo "✓ redline_configs 表创建成功\n";
}

if (!Capsule::schema()->hasTable('audit_logs')) {
    Capsule::schema()->create('audit_logs', function ($table) {
        $table->increments('id');
        $table->integer('user_id')->nullable();
        $table->string('username', 50)->default('');
        $table->string('action', 100);
        $table->string('module', 50)->default('');
        $table->string('platform', 20)->default('');
        $table->string('ip', 45)->default('');
        $table->string('user_agent', 255)->default('');
        $table->string('request_method', 10)->default('GET');
        $table->string('request_path', 255)->default('');
        $table->text('request_params')->nullable();
        $table->integer('response_code')->default(0);
        $table->string('guard_result', 20)->default('passed');
        $table->enum('status', ['success', 'failed', 'blocked'])->default('success');
        $table->text('remark')->nullable();
        $table->timestamps();
        $table->index('user_id');
        $table->index('action');
        $table->index('module');
        $table->index('platform');
        $table->index('created_at');
        $table->index('guard_result');
    });
    echo "✓ audit_logs 表创建成功\n";
}

echo "\n数据表创建完成！\n";

$seedFile = __DIR__ . '/seed.php';
if (file_exists($seedFile)) {
    echo "\n正在导入种子数据...\n";
    require $seedFile;
    echo "种子数据导入完成！\n";
}
