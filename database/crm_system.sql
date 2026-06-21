-- ============================================
-- CRM客户跟进系统 - 数据库初始化脚本
-- ============================================

CREATE DATABASE IF NOT EXISTS crm_system DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crm_system;

-- ============================================
-- 用户表
-- ============================================
DROP TABLE IF EXISTS crm_user;
CREATE TABLE crm_user (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE COMMENT '登录账号',
    password VARCHAR(128) NOT NULL COMMENT '密码(加密)',
    name VARCHAR(64) NOT NULL COMMENT '姓名',
    avatar VARCHAR(255) DEFAULT '' COMMENT '头像',
    role VARCHAR(32) NOT NULL COMMENT '角色: super_admin/admin/sales_manager/sales/client',
    platform VARCHAR(16) NOT NULL COMMENT '平台入口: admin/sales/client',
    phone VARCHAR(32) DEFAULT '' COMMENT '手机号',
    email VARCHAR(128) DEFAULT '' COMMENT '邮箱',
    department VARCHAR(64) DEFAULT '' COMMENT '部门',
    status TINYINT DEFAULT 1 COMMENT '状态:1-启用,0-禁用',
    last_login_at DATETIME DEFAULT NULL COMMENT '最后登录时间',
    last_login_ip VARCHAR(64) DEFAULT '' COMMENT '最后登录IP',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_platform (platform),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统用户表';

-- ============================================
-- 客户表
-- ============================================
DROP TABLE IF EXISTS crm_customer;
CREATE TABLE crm_customer (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL COMMENT '客户姓名',
    company VARCHAR(255) NOT NULL COMMENT '公司名称',
    position VARCHAR(64) DEFAULT '' COMMENT '职位',
    phone VARCHAR(32) DEFAULT '' COMMENT '电话',
    email VARCHAR(128) DEFAULT '' COMMENT '邮箱',
    wechat VARCHAR(64) DEFAULT '' COMMENT '微信',
    address VARCHAR(500) DEFAULT '' COMMENT '地址',
    level CHAR(1) DEFAULT 'C' COMMENT '客户等级: A/B/C',
    status VARCHAR(32) DEFAULT 'potential' COMMENT '状态: potential/active/negotiating/lost',
    source VARCHAR(32) DEFAULT 'manual' COMMENT '来源: online/referral/exhibition/cold_call/manual',
    industry VARCHAR(64) DEFAULT '' COMMENT '行业',
    scale VARCHAR(32) DEFAULT '' COMMENT '规模',
    remark TEXT COMMENT '备注',
    owner_id INT UNSIGNED DEFAULT 0 COMMENT '负责人ID',
    owner_name VARCHAR(64) DEFAULT '' COMMENT '负责人姓名',
    total_amount DECIMAL(12,2) DEFAULT 0 COMMENT '历史成交金额',
    followup_count INT DEFAULT 0 COMMENT '跟进次数',
    last_followup_at DATETIME DEFAULT NULL COMMENT '最后跟进时间',
    next_followup_at DATETIME DEFAULT NULL COMMENT '下次跟进时间',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_owner (owner_id),
    INDEX idx_level (level),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客户信息表';

-- ============================================
-- 跟进记录表
-- ============================================
DROP TABLE IF EXISTS crm_followup;
CREATE TABLE crm_followup (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL COMMENT '客户ID',
    customer_name VARCHAR(128) DEFAULT '' COMMENT '客户姓名',
    type VARCHAR(32) NOT NULL COMMENT '类型: call/meeting/wechat/email/sign',
    content TEXT NOT NULL COMMENT '跟进内容',
    result VARCHAR(500) DEFAULT '' COMMENT '跟进结果',
    followup_time DATETIME NOT NULL COMMENT '跟进时间',
    next_followup DATETIME DEFAULT NULL COMMENT '下次跟进时间',
    attachments VARCHAR(500) DEFAULT '' COMMENT '附件',
    operator_id INT UNSIGNED NOT NULL COMMENT '操作人ID',
    operator_name VARCHAR(64) NOT NULL COMMENT '操作人姓名',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_operator (operator_id),
    INDEX idx_time (followup_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客户跟进记录表';

-- ============================================
-- 商机表
-- ============================================
DROP TABLE IF EXISTS crm_opportunity;
CREATE TABLE crm_opportunity (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL COMMENT '客户ID',
    customer_name VARCHAR(128) DEFAULT '' COMMENT '客户名称',
    name VARCHAR(255) NOT NULL COMMENT '商机名称',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '预估金额',
    stage VARCHAR(32) NOT NULL DEFAULT 'initial' COMMENT '阶段: initial/qualified/proposal/negotiation/won/lost',
    probability TINYINT DEFAULT 0 COMMENT '赢单概率%',
    expected_close DATE DEFAULT NULL COMMENT '预计成交日期',
    description TEXT COMMENT '商机描述',
    owner_id INT UNSIGNED DEFAULT 0 COMMENT '负责人ID',
    owner_name VARCHAR(64) DEFAULT '' COMMENT '负责人姓名',
    won_amount DECIMAL(12,2) DEFAULT 0 COMMENT '实际成交金额',
    won_at DATETIME DEFAULT NULL COMMENT '成交时间',
    lost_reason VARCHAR(500) DEFAULT '' COMMENT '输单原因',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_owner (owner_id),
    INDEX idx_stage (stage),
    INDEX idx_expected (expected_close)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商机表';

-- ============================================
-- 合同表
-- ============================================
DROP TABLE IF EXISTS crm_contract;
CREATE TABLE crm_contract (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_no VARCHAR(64) NOT NULL UNIQUE COMMENT '合同编号',
    customer_id INT UNSIGNED NOT NULL COMMENT '客户ID',
    customer_name VARCHAR(128) DEFAULT '' COMMENT '客户名称',
    opportunity_id INT UNSIGNED DEFAULT 0 COMMENT '关联商机ID',
    name VARCHAR(255) NOT NULL COMMENT '合同名称',
    type VARCHAR(32) DEFAULT 'service' COMMENT '类型',
    amount DECIMAL(12,2) NOT NULL COMMENT '合同金额',
    status VARCHAR(32) DEFAULT 'pending' COMMENT '状态: pending/active/expired/cancelled',
    start_date DATE DEFAULT NULL COMMENT '开始日期',
    end_date DATE DEFAULT NULL COMMENT '结束日期',
    sign_date DATE DEFAULT NULL COMMENT '签署日期',
    owner_id INT UNSIGNED DEFAULT 0 COMMENT '负责人ID',
    owner_name VARCHAR(64) DEFAULT '' COMMENT '负责人姓名',
    remark TEXT COMMENT '备注',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    INDEX idx_sign_date (sign_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合同表';

-- ============================================
-- 审计日志表
-- ============================================
DROP TABLE IF EXISTS crm_audit_log;
CREATE TABLE crm_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT 0 COMMENT '用户ID',
    username VARCHAR(64) DEFAULT '' COMMENT '用户名',
    platform VARCHAR(16) DEFAULT '' COMMENT '平台',
    action VARCHAR(64) NOT NULL COMMENT '操作类型',
    action_label VARCHAR(128) DEFAULT '' COMMENT '操作描述',
    target_type VARCHAR(64) DEFAULT '' COMMENT '目标类型',
    target_id VARCHAR(64) DEFAULT '' COMMENT '目标ID',
    request_uri VARCHAR(500) DEFAULT '' COMMENT '请求URI',
    request_method VARCHAR(16) DEFAULT '' COMMENT '请求方法',
    ip VARCHAR(64) DEFAULT '' COMMENT 'IP地址',
    device_fingerprint VARCHAR(128) DEFAULT '' COMMENT '设备指纹',
    user_agent VARCHAR(500) DEFAULT '' COMMENT 'UA',
    status VARCHAR(16) DEFAULT 'success' COMMENT '状态: success/failed/blocked',
    fail_reason VARCHAR(500) DEFAULT '' COMMENT '失败原因',
    request_data TEXT COMMENT '请求数据(JSON)',
    response_data TEXT COMMENT '响应数据(JSON)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_platform (platform),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='审计日志表';

-- ============================================
-- License 配置表
-- ============================================
DROP TABLE IF EXISTS crm_license;
CREATE TABLE crm_license (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(128) NOT NULL UNIQUE COMMENT 'License Key',
    edition VARCHAR(32) NOT NULL COMMENT '版本: standard/professional/enterprise',
    max_users INT DEFAULT 100 COMMENT '最大用户数',
    max_clients INT DEFAULT 10000 COMMENT '最大客户数',
    features TEXT COMMENT '可用功能列表(JSON)',
    issued_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '签发日期',
    expire_at DATETIME NOT NULL COMMENT '过期日期',
    status TINYINT DEFAULT 1 COMMENT '状态:1-有效,0-无效',
    remark TEXT COMMENT '备注',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='License配置表';

-- ============================================
-- IP 白名单表
-- ============================================
DROP TABLE IF EXISTS crm_ip_whitelist;
CREATE TABLE crm_ip_whitelist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_range VARCHAR(64) NOT NULL UNIQUE COMMENT 'IP或网段',
    platform VARCHAR(16) DEFAULT '*' COMMENT '适用平台: admin/sales/client/*',
    remark VARCHAR(255) DEFAULT '' COMMENT '备注',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IP白名单表';

-- ============================================
-- 数据审核主表
-- ============================================
DROP TABLE IF EXISTS crm_audit_record;
CREATE TABLE crm_audit_record (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_no VARCHAR(64) NOT NULL UNIQUE COMMENT '审核单号',
    target_type VARCHAR(32) NOT NULL COMMENT '审核目标类型: customer/opportunity/contract',
    target_id INT UNSIGNED DEFAULT 0 COMMENT '目标记录ID(新建时为0)',
    operation_type VARCHAR(32) NOT NULL COMMENT '操作类型: create/update/delete/level_upgrade/stage_change/won/lost/sign/cancel',
    operation_label VARCHAR(64) DEFAULT '' COMMENT '操作描述',
    data_before TEXT COMMENT '变更前数据(JSON)',
    data_after TEXT COMMENT '变更后数据(JSON)',
    change_summary VARCHAR(500) DEFAULT '' COMMENT '变更摘要',
    status VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT '审核状态: pending/approved/rejected/writeback_success/writeback_failed',
    submitter_id INT UNSIGNED NOT NULL COMMENT '提交人ID',
    submitter_name VARCHAR(64) NOT NULL COMMENT '提交人姓名',
    submitter_platform VARCHAR(16) NOT NULL COMMENT '提交端: admin/sales/client',
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
    auditor_id INT UNSIGNED DEFAULT 0 COMMENT '审核人ID',
    auditor_name VARCHAR(64) DEFAULT '' COMMENT '审核人姓名',
    audit_remark VARCHAR(500) DEFAULT '' COMMENT '审核意见',
    audited_at DATETIME DEFAULT NULL COMMENT '审核时间',
    writeback_attempts TINYINT DEFAULT 0 COMMENT '回写尝试次数',
    writeback_error VARCHAR(500) DEFAULT '' COMMENT '回写错误信息',
    writeback_at DATETIME DEFAULT NULL COMMENT '回写时间',
    remark VARCHAR(255) DEFAULT '' COMMENT '备注',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_target (target_type, target_id),
    INDEX idx_status (status),
    INDEX idx_submitter (submitter_id),
    INDEX idx_auditor (auditor_id),
    INDEX idx_submitted (submitted_at),
    INDEX idx_platform (submitter_platform),
    INDEX idx_operation (operation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='数据审核主表';

-- ============================================
-- 审核操作日志表
-- ============================================
DROP TABLE IF EXISTS crm_audit_log;
CREATE TABLE crm_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_id BIGINT UNSIGNED NOT NULL COMMENT '审核记录ID',
    audit_no VARCHAR(64) DEFAULT '' COMMENT '审核单号',
    action VARCHAR(32) NOT NULL COMMENT '操作: submit/approve/reject/writeback_success/writeback_failed/retry',
    action_label VARCHAR(64) DEFAULT '' COMMENT '操作描述',
    operator_id INT UNSIGNED DEFAULT 0 COMMENT '操作人ID',
    operator_name VARCHAR(64) DEFAULT '' COMMENT '操作人姓名',
    operator_platform VARCHAR(16) DEFAULT '' COMMENT '操作端',
    remark VARCHAR(500) DEFAULT '' COMMENT '备注/意见',
    extra_data TEXT COMMENT '扩展数据(JSON)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit (audit_id),
    INDEX idx_action (action),
    INDEX idx_operator (operator_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='审核操作日志表';

-- ============================================
-- 三端红线规则配置表
-- ============================================
DROP TABLE IF EXISTS crm_redline_config;
CREATE TABLE crm_redline_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform VARCHAR(16) NOT NULL UNIQUE COMMENT '平台: admin/sales/client',
    enabled TINYINT DEFAULT 1 COMMENT '是否启用',
    ip_whitelist TEXT COMMENT 'IP白名单(JSON数组)',
    ip_whitelist_enforce TINYINT DEFAULT 0 COMMENT '是否强制IP白名单',
    access_hours_start VARCHAR(8) DEFAULT '00:00' COMMENT '允许访问开始时间',
    access_hours_end VARCHAR(8) DEFAULT '23:59' COMMENT '允许访问结束时间',
    access_hours_enforce TINYINT DEFAULT 0 COMMENT '是否强制访问时段',
    max_requests_per_minute INT DEFAULT 300 COMMENT '每分钟最大请求数',
    session_timeout INT DEFAULT 7200 COMMENT '会话超时时间(秒)',
    require_device_fingerprint TINYINT DEFAULT 0 COMMENT '是否要求设备指纹',
    device_fingerprint_threshold DECIMAL(3,2) DEFAULT 0.60 COMMENT '设备指纹相似度阈值',
    allow_multi_device_login TINYINT DEFAULT 1 COMMENT '是否允许多设备登录',
    sensitive_operation_2fa TINYINT DEFAULT 0 COMMENT '敏感操作是否需要二次验证',
    updated_by INT UNSIGNED DEFAULT 0 COMMENT '更新人ID',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_platform (platform),
    INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='三端红线规则配置表';

-- ============================================
-- 初始化数据
-- ============================================

-- 默认用户 (密码: admin123/sales123/client123 实际需加密)
INSERT INTO crm_user (username, password, name, role, platform, phone, email, status) VALUES
('admin', 'e10adc3949ba59abbe56e057f20f883e', '系统管理员', 'super_admin', 'admin', '13800000000', 'admin@crm.com', 1),
('sales01', 'e10adc3949ba59abbe56e057f20f883e', '张销售', 'sales_manager', 'sales', '13800138001', 'sales01@crm.com', 1),
('sales02', 'e10adc3949ba59abbe56e057f20f883e', '李销售', 'sales', 'sales', '13800138002', 'sales02@crm.com', 1),
('client01', 'e10adc3949ba59abbe56e057f20f883e', '王客户', 'client', 'client', '13800138008', 'client@example.com', 1);

-- 初始License
INSERT INTO crm_license (license_key, edition, max_users, max_clients, features, expire_at) VALUES
('CRM-LICENSE-2026-STD', 'standard', 100, 10000,
 '["customer_basic","followup_basic","dashboard_basic"]',
 '2027-06-21 23:59:59');

-- IP白名单
INSERT INTO crm_ip_whitelist (ip_range, platform, remark) VALUES
('127.0.0.1', '*', '本地回环'),
('::1', '*', 'IPv6本地'),
('192.168.0.0/16', '*', '内网网段'),
('10.0.0.0/8', '*', '内网网段');

-- 初始客户
INSERT INTO crm_customer (name, company, position, phone, email, level, status, source, industry, scale, owner_id, owner_name, total_amount, followup_count, created_at) VALUES
('张伟', '腾讯科技有限公司', '技术总监', '13800138001', 'zhangwei@tencent.com', 'A', 'active', 'referral', '互联网', '10000+', 2, '张销售', 580000, 12, '2026-01-15 09:30:00'),
('李娜', '阿里巴巴集团', '采购经理', '13800138002', 'lina@alibaba.com', 'A', 'active', 'online', '电商', '10000+', 2, '张销售', 1200000, 28, '2026-02-20 10:15:00'),
('王强', '字节跳动科技', '产品经理', '13800138003', 'wangqiang@bytedance.com', 'B', 'potential', 'exhibition', '互联网', '10000+', 3, '李销售', 0, 3, '2026-03-10 14:00:00');

-- 初始跟进记录
INSERT INTO crm_followup (customer_id, customer_name, type, content, followup_time, operator_id, operator_name) VALUES
(1, '张伟', 'meeting', '上门洽谈年度合作框架，客户对方案较为满意，预计下周确认合同细节', '2026-06-18 14:30:00', 2, '张销售'),
(2, '李娜', 'call', '电话确认报价条款，客户要求增加售后服务模块，需重新核算成本', '2026-06-20 10:15:00', 2, '张销售');

-- 初始商机
INSERT INTO crm_opportunity (customer_id, customer_name, name, amount, stage, probability, expected_close, description, owner_id, owner_name) VALUES
(2, '李娜', '2026年度CRM系统采购项目', 1200000, 'negotiation', 75, '2026-07-15', '客户计划Q3完成采购，涉及50个用户授权', 2, '张销售'),
(1, '张伟', 'SaaS平台定制化开发', 580000, 'proposal', 50, '2026-08-01', '已提交初步方案，等待客户技术评审', 2, '张销售');

-- 初始红线配置
INSERT INTO crm_redline_config (platform, enabled, ip_whitelist, ip_whitelist_enforce, access_hours_start, access_hours_end, access_hours_enforce, max_requests_per_minute, session_timeout, require_device_fingerprint, device_fingerprint_threshold, allow_multi_device_login, sensitive_operation_2fa) VALUES
('admin', 1, '["127.0.0.1","::1","192.168.0.0/16","10.0.0.0/8"]', 1, '08:00', '22:00', 0, 100, 1800, 1, 0.80, 0, 1),
('sales', 1, '[]', 0, '07:00', '23:00', 0, 200, 3600, 0, 0.60, 1, 0),
('client', 1, '[]', 0, '00:00', '23:59', 0, 60, 86400, 0, 0.50, 1, 1);

-- 初始审核记录示例
INSERT INTO crm_audit_record (audit_no, target_type, target_id, operation_type, operation_label, data_before, data_after, change_summary, status, submitter_id, submitter_name, submitter_platform, submitted_at) VALUES
('AUD202606220001', 'customer', 3, 'level_upgrade', '客户等级升级', '{"level":"C"}', '{"level":"B"}', '客户等级从C升级到B', 'pending', 3, '李销售', 'sales', '2026-06-22 10:30:00'),
('AUD202606220002', 'customer', 0, 'create', '新增客户', '{}', '{"name":"赵六","company":"小米科技","phone":"13800138006","level":"B"}', '新增客户：赵六-小米科技', 'approved', 2, '张销售', 'sales', '2026-06-21 14:00:00');
