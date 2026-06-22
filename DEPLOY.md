# CRM客户跟进系统 - 部署文档

## 一、平台定位核心原则

### 1.1 三端定位模型

本系统采用严格的三端分离架构，每端具有独立的入口、权限边界和安全策略：

| 端标识 | 名称 | 定位 | 核心模块 | 默认端口 |
|--------|------|------|----------|----------|
| `admin` | 管理端 | 系统管理员使用，负责用户管理、配置管理、审计、License管理 | customer、user、report、system、license、audit、redline | 8081 |
| `sales` | 销售端 | 销售人员使用，负责客户跟进、商机管理、合同签约 | customer、followup、opportunity、contract、dashboard、audit | 8080 |
| `client` | 客户端 | 企业客户使用，查看合同、发票、服务工单 | profile、contract、invoice、service、audit | 8082 |

### 1.2 平台标识传递机制

**前端 → 后端**：通过 HTTP 请求头 `X-Platform-Type` 传递入口端标识

**前端注入优先级**（[request.js](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/frontend/src/api/request.js#L7-L9)）：
1. HTML Meta 标签：`<meta name="platform" content="admin">`
2. Vite 环境变量：`VITE_PLATFORM`
3. 默认值：`sales`

**后端校验**（[PlatformGuard.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/api/guard/PlatformGuard.php#L21-L92)）：
- 白名单端点（`/api/auth/*`、`/api/`）跳过平台校验
- 受保护端点必须携带合法 `X-Platform-Type` 请求头
- 仅允许 `admin`、`sales`、`client` 三种取值（大小写不敏感）
- 严格校验端点边界，禁止跨端越权访问

### 1.3 边界违规错误码

| 错误码 | 类型 | 说明 |
|--------|------|------|
| 4001 | platform_type_missing | 入口端标识缺失 |
| 4002 | platform_type_invalid | 入口端标识无效 |
| 4003 | platform_boundary_violation | 平台定位越界 |
| 4004 | endpoint_config_missing | 平台端点配置缺失 |

---

## 二、三端入口红线校验环境变量

### 2.1 后端配置文件

后端红线校验配置位于：[api/config/config.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/api/config/config.php)

支持通过环境变量覆盖默认配置。部署时建议创建 `.env.php` 或修改 `config.php` 中的对应常量。

### 2.2 红线校验全局配置（环境变量映射）

| 常量名 | 环境变量 | 类型 | 默认值 | 说明 |
|--------|----------|------|--------|------|
| `RED_LINE_IP_WHITELIST` | `RED_LINE_IP_WHITELIST` | array | `['127.0.0.1','::1','192.168.0.0/16','10.0.0.0/8']` | 全局IP白名单，支持CIDR |
| `RED_LINE_ACCESS_HOURS` | `RED_LINE_ACCESS_HOURS_START`<br>`RED_LINE_ACCESS_HOURS_END` | array | `00:00 - 23:59` | 全局允许访问时段 |
| `RED_LINE_MAX_REQUESTS_PER_MINUTE` | `RED_LINE_MAX_REQUESTS_PER_MINUTE` | int | 300 | 全局每分钟请求上限 |
| `RED_LINE_SESSION_TIMEOUT` | `RED_LINE_SESSION_TIMEOUT` | int | 7200 | 全局会话超时（秒） |
| `JWT_SECRET` | `JWT_SECRET` | string | **生产环境必须修改** | JWT签名密钥 |
| `JWT_EXPIRE` | `JWT_EXPIRE` | int | 7200 | Token有效期（秒） |

### 2.3 管理端（admin）红线配置

对应常量数组：`RED_LINE_PLATFORM_CONFIG['admin']`

| 配置项 | 环境变量 | 类型 | 默认值 | 说明 |
|--------|----------|------|--------|------|
| `enabled` | `ADMIN_REDLINE_ENABLED` | bool | true | 红线校验总开关 |
| `ip_whitelist` | `ADMIN_IP_WHITELIST` | array | `['127.0.0.1','::1','192.168.0.0/16','10.0.0.0/8']` | 管理端IP白名单 |
| `ip_whitelist_enforce` | `ADMIN_IP_WHITELIST_ENFORCE` | bool | **true** | 是否强制IP白名单 |
| `access_hours` | `ADMIN_ACCESS_HOURS_START`<br>`ADMIN_ACCESS_HOURS_END` | array | `08:00 - 22:00` | 管理端访问时段 |
| `access_hours_enforce` | `ADMIN_ACCESS_HOURS_ENFORCE` | bool | false | 是否强制访问时段 |
| `max_requests_per_minute` | `ADMIN_MAX_REQUESTS_PER_MINUTE` | int | 100 | 管理端限流阈值 |
| `session_timeout` | `ADMIN_SESSION_TIMEOUT` | int | 1800 | 管理端会话超时（30分钟） |
| `require_device_fingerprint` | `ADMIN_REQUIRE_DEVICE_FP` | bool | **true** | 是否强制设备指纹 |
| `device_fingerprint_threshold` | `ADMIN_DEVICE_FP_THRESHOLD` | float | 0.8 | 设备指纹相似度阈值 |
| `allow_multi_device_login` | `ADMIN_ALLOW_MULTI_DEVICE` | bool | **false** | 是否允许多设备登录 |
| `sensitive_operation_2fa` | `ADMIN_SENSITIVE_2FA` | bool | true | 敏感操作是否需二次验证 |

### 2.4 销售端（sales）红线配置

对应常量数组：`RED_LINE_PLATFORM_CONFIG['sales']`

| 配置项 | 环境变量 | 类型 | 默认值 | 说明 |
|--------|----------|------|--------|------|
| `enabled` | `SALES_REDLINE_ENABLED` | bool | true | 红线校验总开关 |
| `ip_whitelist` | `SALES_IP_WHITELIST` | array | `[]` | 销售端IP白名单 |
| `ip_whitelist_enforce` | `SALES_IP_WHITELIST_ENFORCE` | bool | false | 是否强制IP白名单 |
| `access_hours` | `SALES_ACCESS_HOURS_START`<br>`SALES_ACCESS_HOURS_END` | array | `07:00 - 23:00` | 销售端访问时段 |
| `access_hours_enforce` | `SALES_ACCESS_HOURS_ENFORCE` | bool | false | 是否强制访问时段 |
| `max_requests_per_minute` | `SALES_MAX_REQUESTS_PER_MINUTE` | int | 200 | 销售端限流阈值 |
| `session_timeout` | `SALES_SESSION_TIMEOUT` | int | 3600 | 销售端会话超时（1小时） |
| `require_device_fingerprint` | `SALES_REQUIRE_DEVICE_FP` | bool | false | 是否强制设备指纹 |
| `device_fingerprint_threshold` | `SALES_DEVICE_FP_THRESHOLD` | float | 0.6 | 设备指纹相似度阈值 |
| `allow_multi_device_login` | `SALES_ALLOW_MULTI_DEVICE` | bool | true | 是否允许多设备登录 |
| `sensitive_operation_2fa` | `SALES_SENSITIVE_2FA` | bool | false | 敏感操作是否需二次验证 |

### 2.5 客户端（client）红线配置

对应常量数组：`RED_LINE_PLATFORM_CONFIG['client']`

| 配置项 | 环境变量 | 类型 | 默认值 | 说明 |
|--------|----------|------|--------|------|
| `enabled` | `CLIENT_REDLINE_ENABLED` | bool | true | 红线校验总开关 |
| `ip_whitelist` | `CLIENT_IP_WHITELIST` | array | `[]` | 客户端IP白名单 |
| `ip_whitelist_enforce` | `CLIENT_IP_WHITELIST_ENFORCE` | bool | false | 是否强制IP白名单 |
| `access_hours` | `CLIENT_ACCESS_HOURS_START`<br>`CLIENT_ACCESS_HOURS_END` | array | `00:00 - 23:59` | 客户端访问时段 |
| `access_hours_enforce` | `CLIENT_ACCESS_HOURS_ENFORCE` | bool | false | 是否强制访问时段 |
| `max_requests_per_minute` | `CLIENT_MAX_REQUESTS_PER_MINUTE` | int | 60 | 客户端限流阈值 |
| `session_timeout` | `CLIENT_SESSION_TIMEOUT` | int | 86400 | 客户端会话超时（24小时） |
| `require_device_fingerprint` | `CLIENT_REQUIRE_DEVICE_FP` | bool | false | 是否强制设备指纹 |
| `device_fingerprint_threshold` | `CLIENT_DEVICE_FP_THRESHOLD` | float | 0.5 | 设备指纹相似度阈值 |
| `allow_multi_device_login` | `CLIENT_ALLOW_MULTI_DEVICE` | bool | true | 是否允许多设备登录 |
| `sensitive_operation_2fa` | `CLIENT_SENSITIVE_2FA` | bool | true | 敏感操作是否需二次验证 |

### 2.6 红线触发错误码

| 错误码 | 类型 | 说明 |
|--------|------|------|
| 4201 | identity_invalid | 身份凭证无效 |
| 4202 | device_fingerprint_mismatch | 设备指纹不匹配 |
| 4203 | ip_blocked | IP不在白名单 |
| 4204 | access_outside_hours | 非允许访问时段 |
| 4205 | rate_limit_exceeded | 请求频率超限 |
| 4206 | session_expired | 会话已过期 |
| 4207 | token_forged | Token被篡改 |
| 4208 | multi_device_login | 多设备登录被拒绝 |
| 4209 | device_fingerprint_required | 要求设备指纹校验 |
| 4210 | platform_redline_disabled | 当前端红线校验未启用 |
| 4211 | platform_missing | 入口端标识缺失 |
| 4212 | config_load_failed | 红线配置加载失败 |
| 4213 | session_start_failed | Session启动失败 |
| 4214 | token_payload_invalid | Token payload格式异常 |

### 2.7 前端环境变量

前端环境变量通过 `.env.{mode}` 文件配置，对应 Vite 的 `--mode` 参数：

#### `.env.admin` - 管理端环境变量
```
VITE_PLATFORM=admin
VITE_API_BASE=/api
VITE_APP_TITLE=CRM系统 - 管理端
```

#### `.env.sales` - 销售端环境变量
```
VITE_PLATFORM=sales
VITE_API_BASE=/api
VITE_APP_TITLE=CRM系统 - 销售端
```

#### `.env.client` - 客户端环境变量
```
VITE_PLATFORM=client
VITE_API_BASE=/api
VITE_APP_TITLE=CRM系统 - 客户端
```

---

## 三、部署流程

### 3.1 后端部署（PHP）

**环境要求**：
- PHP >= 7.4
- MySQL/MariaDB >= 5.7
- Apache/Nginx（启用 URL Rewrite）
- PDO MySQL 扩展

**部署步骤**：

```bash
# 1. 克隆项目
cd /var/www
git clone <repository-url> crm-system
cd crm-system

# 2. 导入数据库
mysql -u root -p < database/crm_system.sql

# 3. 配置数据库和红线参数
cp api/config/config.php api/config/config.local.php
# 编辑 api/config/config.php 或使用环境变量覆盖：
# - DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
# - JWT_SECRET （生产环境必须修改！）
# - RED_LINE_*  各端红线参数

# 4. 设置目录权限
chmod -R 755 api/
chmod -R 775 api/storage/

# 5. 启动 PHP 内置服务器（开发环境）
cd api && php -S 0.0.0.0:8000 index.php

# 6. Nginx 配置示例（生产环境）
# 见附录 A
```

### 3.2 前端部署（Vue3 + Vite）

**环境要求**：
- Node.js >= 16
- npm >= 8

**部署步骤**：

```bash
# 1. 安装依赖
cd frontend
npm install

# 2. 配置环境变量（创建各端 .env 文件）
# 参考 2.7 节

# 3. 分别构建三端
npm run build:admin    # 构建管理端，输出 dist/admin/
npm run build:sales    # 构建销售端，输出 dist/sales/
npm run build:client   # 构建客户端，输出 dist/client/

# 4. 部署到 Nginx
# 管理端:  8081 → dist/admin/
# 销售端:  8080 → dist/sales/
# 客户端:  8082 → dist/client/
# 或通过子路径部署: /admin /sales /client
```

---

## 四、验收命令

### 4.1 后端单元测试

运行全部红线与平台守卫测试套件：

```bash
cd /path/to/project

# 运行全部测试
php tests/run.php

# 仅运行平台定位测试
php tests/run.php PlatformGuardTest

# 仅运行红线校验测试
php tests/run.php RedLineGuardTest

# 仅运行商用边界测试
php tests/run.php CommercialGuardTest

# 运行状态闭环测试
php tests/run.php StateClosedLoopTest

# 运行多个测试类
php tests/run.php PlatformGuardTest RedLineGuardTest
```

**预期结果**：所有测试用例通过，退出码为 0。

### 4.2 平台定位核心原则验收

通过 cURL 模拟各端请求进行验收：

```bash
BASE_URL="http://localhost:8000"

# ========== 测试 1：白名单端点无需平台标识 ==========
echo "[TEST 1] 白名单端点无需平台标识"
curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/api/auth/platform-info"
echo "  (预期: 200)"

# ========== 测试 2：无平台标识访问受保护端点 ==========
echo "[TEST 2] 无平台标识访问受保护端点 (预期错误码 4001)"
curl -s "$BASE_URL/api/customer/list" | python3 -m json.tool

# ========== 测试 3：无效平台标识 ==========
echo "[TEST 3] 无效平台标识 (预期错误码 4002)"
curl -s -H "X-Platform-Type: invalid" "$BASE_URL/api/customer/list" | python3 -m json.tool

# ========== 测试 4：sales 端越权访问 admin 模块 ==========
echo "[TEST 4] sales 越权访问 admin/user (预期错误码 4003)"
curl -s -H "X-Platform-Type: sales" "$BASE_URL/api/admin/user/list" | python3 -m json.tool

# ========== 测试 5：client 端越权访问 customer 模块 ==========
echo "[TEST 5] client 越权访问 customer (预期错误码 4003)"
curl -s -H "X-Platform-Type: client" "$BASE_URL/api/customer/list" | python3 -m json.tool

# ========== 测试 6：admin 端合法访问 admin 模块 ==========
echo "[TEST 6] admin 合法访问 admin/user (需要登录)"
curl -s -H "X-Platform-Type: admin" -H "Authorization: Bearer <token>" "$BASE_URL/api/admin/user/list" | python3 -m json.tool

# ========== 测试 7：sales 端合法访问 followup 模块 ==========
echo "[TEST 7] sales 合法访问 followup (需要登录)"
curl -s -H "X-Platform-Type: sales" -H "Authorization: Bearer <token>" "$BASE_URL/api/followup/list" | python3 -m json.tool

# ========== 测试 8：client 端合法访问 profile ==========
echo "[TEST 8] client 合法访问 client/profile (需要登录)"
curl -s -H "X-Platform-Type: client" -H "Authorization: Bearer <token>" "$BASE_URL/api/client/profile" | python3 -m json.tool
```

### 4.3 三端入口红线校验验收

```bash
BASE_URL="http://localhost:8000"

# 辅助函数：登录获取 token
login() {
  local platform=$1
  local username=$2
  local password=$3
  curl -s -X POST \
    -H "X-Platform-Type: $platform" \
    -H "Content-Type: application/json" \
    -d "{\"username\":\"$username\",\"password\":\"$password\"}" \
    "$BASE_URL/api/auth/login" | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('token',''))"
}

# ========== 红线测试：管理端 ==========
ADMIN_TOKEN=$(login admin "super_admin" "password123")
echo "管理端 Token: ${ADMIN_TOKEN:0:16}..."

# 测试 admin IP 白名单（从非白名单IP访问）
echo "[RED-1] admin 非白名单IP访问 (预期错误码 4203)"
curl -s -H "X-Platform-Type: admin" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "X-Forwarded-For: 8.8.8.8" \
  "$BASE_URL/api/customer/list" | python3 -m json.tool

# 测试 admin 强制设备指纹
echo "[RED-2] admin 无设备指纹 (预期错误码 4209)"
curl -s -H "X-Platform-Type: admin" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  "$BASE_URL/api/customer/list" | python3 -m json.tool

# 测试 admin 携带合法设备指纹通过
ADMIN_FP=$(echo -n "test-agent|zh-CN|gzip|127.0.0.1" | md5sum | awk '{print $1}')
echo "[RED-3] admin 携带合法设备指纹通过"
curl -s -H "X-Platform-Type: admin" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "X-Device-Fingerprint: $ADMIN_FP" \
  -H "User-Agent: test-agent" \
  -H "Accept-Language: zh-CN" \
  -H "Accept-Encoding: gzip" \
  "$BASE_URL/api/customer/list" | python3 -c "import sys,json;d=json.load(sys.stdin);print('code:',d.get('code'),'message:',d.get('message'))"

# ========== 红线测试：销售端 ==========
SALES_TOKEN=$(login sales "sales001" "password123")
echo "销售端 Token: ${SALES_TOKEN:0:16}..."

# 测试 sales 限流（快速发送 201 次请求）
echo "[RED-4] sales 限流测试 (第 201 次预期 4205)"
for i in $(seq 1 202); do
  CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "X-Platform-Type: sales" \
    -H "Authorization: Bearer $SALES_TOKEN" \
    "$BASE_URL/api/dashboard/stats")
  if [ "$i" -eq 201 ]; then
    echo "第 $i 次请求状态: $CODE (预期触发限流)"
  fi
done

# ========== 红线测试：Token 校验 ==========
# 测试伪造 Token
echo "[RED-5] 伪造 Token (预期错误码 4207)"
curl -s -H "X-Platform-Type: sales" \
  -H "Authorization: Bearer forged.token.here" \
  "$BASE_URL/api/customer/list" | python3 -c "import sys,json;d=json.load(sys.stdin);print('code:',d.get('code'),'message:',d.get('message'))"

# 测试 Token 平台不匹配
echo "[RED-6] Token平台不匹配 (admin token 访问 sales，预期错误码 4201)"
curl -s -H "X-Platform-Type: sales" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  "$BASE_URL/api/customer/list" | python3 -c "import sys,json;d=json.load(sys.stdin);print('code:',d.get('code'),'message:',d.get('message'))"

# ========== 红线测试：客户端 ==========
CLIENT_TOKEN=$(login client "client001" "password123")
echo "客户端 Token: ${CLIENT_TOKEN:0:16}..."

# 测试 client 会话超时（默认24小时，配置修改后验证）
echo "[RED-7] client 合法访问 profile"
curl -s -H "X-Platform-Type: client" \
  -H "Authorization: Bearer $CLIENT_TOKEN" \
  "$BASE_URL/api/client/profile" | python3 -c "import sys,json;d=json.load(sys.stdin);print('code:',d.get('code'),'message:',d.get('message'))"
```

### 4.4 前端构建与启动验收

```bash
cd frontend

echo "===== 前端构建验收 ====="

# 1. 安装依赖（如未安装）
if [ ! -d "node_modules" ]; then
  echo "[FRONT-1] 安装依赖"
  npm install
fi

# 2. 构建管理端
echo "[FRONT-2] 构建管理端 (platform=admin)"
npm run build:admin
echo "管理端构建状态: $?"
ls -la dist/admin/ | head -5

# 3. 构建销售端
echo "[FRONT-3] 构建销售端 (platform=sales)"
npm run build:sales
echo "销售端构建状态: $?"
ls -la dist/sales/ | head -5

# 4. 构建客户端
echo "[FRONT-4] 构建客户端 (platform=client)"
npm run build:client
echo "客户端构建状态: $?"
ls -la dist/client/ | head -5

# 5. 验证各端入口 HTML 中的 platform meta
echo "[FRONT-5] 验证入口 HTML 平台标识"
echo "admin.html  platform: $(grep -o 'name=\"platform\" content=\"[^\"]*\"' admin.html | grep -o 'content=\"[^\"]*\"' | cut -d'\"' -f2)"
echo "sales.html  platform: $(grep -o 'name=\"platform\" content=\"[^\"]*\"' sales.html | grep -o 'content=\"[^\"]*\"' | cut -d'\"' -f2)"
echo "client.html platform: $(grep -o 'name=\"platform\" content=\"[^\"]*\"' client.html | grep -o 'content=\"[^\"]*\"' | cut -d'\"' -f2)"

# 6. 开发模式启动验证（可选，需要后台运行）
echo "[FRONT-6] 开发模式端口校验"
echo "管理端端口: 8081 (VITE_PLATFORM=admin)"
echo "销售端端口: 8080 (VITE_PLATFORM=sales)"
echo "客户端端口: 8082 (VITE_PLATFORM=client)"
```

### 4.5 一键验收脚本

将以下命令保存为 `acceptance.sh` 并执行：

```bash
#!/bin/bash
set -e

PROJECT_ROOT="$(cd "$(dirname "$0")" && pwd)"
echo "========================================"
echo " CRM系统 - 平台定位与红线校验验收脚本"
echo "========================================"
echo ""

echo "[1/5] 运行后端单元测试..."
cd "$PROJECT_ROOT"
php tests/run.php
echo ""

echo "[2/5] 平台定位核心原则验证..."
echo "详见 4.2 节 cURL 验收命令"
echo ""

echo "[3/5] 三端红线校验验证..."
echo "详见 4.3 节红线验收命令"
echo ""

echo "[4/5] 前端构建验证..."
cd "$PROJECT_ROOT/frontend"
echo "  构建管理端..."
npm run build:admin > /dev/null 2>&1 && echo "  ✓ 管理端构建成功" || echo "  ✗ 管理端构建失败"
echo "  构建销售端..."
npm run build:sales > /dev/null 2>&1 && echo "  ✓ 销售端构建成功" || echo "  ✗ 销售端构建失败"
echo "  构建客户端..."
npm run build:client > /dev/null 2>&1 && echo "  ✓ 客户端构建成功" || echo "  ✗ 客户端构建失败"
echo ""

echo "[5/5] 配置完整性检查..."
cd "$PROJECT_ROOT"
echo "  检查红线配置文件 api/config/config.php"
php -r "
require 'api/config/config.php';
echo '  ✓ PLATFORM_ENDPOINTS: ' . json_encode(array_keys(PLATFORM_ENDPOINTS)) . PHP_EOL;
echo '  ✓ RED_LINE_PLATFORM_CONFIG 三端配置: ' . json_encode(array_keys(RED_LINE_PLATFORM_CONFIG)) . PHP_EOL;
echo '  ✓ JWT_SECRET 已定义: ' . (defined('JWT_SECRET') ? '是' : '否') . PHP_EOL;
\$all = RedLineGuard::getAllPlatformRedLineStatus();
echo '  ✓ 各端红线状态已加载' . PHP_EOL;
"
echo ""

echo "========================================"
echo " 验收完成！"
echo "========================================"
```

### 4.6 验收检查清单

| 检查项 | 验收标准 | 命令/方法 | 状态 |
|--------|----------|-----------|------|
| 平台标识传递 | 前端自动注入 `X-Platform-Type` 请求头 | 浏览器 DevTools Network | ☐ |
| 平台边界隔离 | sales/client 无法访问 admin 模块 | cURL 测试 4-5 | ☐ |
| admin IP 白名单 | 非白名单IP被拒绝（错误码4203） | cURL 测试 RED-1 | ☐ |
| admin 设备指纹 | 无指纹被拒绝（错误码4209） | cURL 测试 RED-2 | ☐ |
| admin 单设备登录 | 多设备被拒绝（错误码4208） | 双设备登录测试 | ☐ |
| sales 限流 | 超限被拒绝（错误码4205） | cURL 测试 RED-4 | ☐ |
| Token 防篡改 | 伪造Token被拒绝（错误码4207） | cURL 测试 RED-5 | ☐ |
| Token 平台绑定 | 跨端Token被拒绝（错误码4201） | cURL 测试 RED-6 | ☐ |
| 会话超时 | 超时需重新登录（错误码4206） | 等待超时后测试 | ☐ |
| 单元测试通过 | 所有测试通过，退出码0 | `php tests/run.php` | ☐ |
| 三端构建成功 | dist/{admin,sales,client} 生成 | 构建验收 | ☐ |
| 入口HTML标识 | 三端 meta platform 正确 | 查看 HTML 源码 | ☐ |
| JWT_SECRET | 生产环境已修改默认值 | 检查 config.php | ☐ |

---

## 附录 A：Nginx 配置示例

```nginx
# 管理端 8081
server {
    listen 8081;
    server_name _;
    root /var/www/crm-system/frontend/dist/admin;
    index index.html;

    location / {
        try_files $uri $uri/ /admin.html;
    }

    location /api {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# 销售端 8080
server {
    listen 8080;
    server_name _;
    root /var/www/crm-system/frontend/dist/sales;
    index index.html;

    location / {
        try_files $uri $uri/ /sales.html;
    }

    location /api {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# 客户端 8082
server {
    listen 8082;
    server_name _;
    root /var/www/crm-system/frontend/dist/client;
    index index.html;

    location / {
        try_files $uri $uri/ /client.html;
    }

    location /api {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 附录 B：商用 License 配置（补充）

| 常量名 | 环境变量 | 默认值 | 说明 |
|--------|----------|--------|------|
| `LICENSE_KEY` | `LICENSE_KEY` | `CRM-LICENSE-2026-STD` | License Key |
| `LICENSE_EXPIRE` | `LICENSE_EXPIRE` | `2027-06-21` | 过期日期 |
| `LICENSE_MAX_USERS` | `LICENSE_MAX_USERS` | 100 | 最大用户数 |
| `LICENSE_MAX_CLIENTS` | `LICENSE_MAX_CLIENTS` | 10000 | 最大客户数 |
